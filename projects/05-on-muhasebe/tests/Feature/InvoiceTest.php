<?php

namespace Tests\Feature;

use App\Actions\RecordPayment;
use App\Models\Contact;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class InvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Contact $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->customer = Contact::factory()->create();
    }

    private function invoicePayload(array $overrides = []): array
    {
        return [
            'type' => 'sales',
            'contact_id' => $this->customer->id,
            'issue_date' => '2026-03-10',
            'due_date' => '2026-04-09',
            'lines' => [
                ['description' => 'Web tasarımı', 'quantity' => '1', 'unit_price' => '18500', 'discount_rate' => 10, 'vat_rate' => 20],
                ['description' => 'Kitap', 'quantity' => '2,5', 'unit_price' => '349,90', 'discount_rate' => 0, 'vat_rate' => 0],
            ],
            ...$overrides,
        ];
    }

    public function test_invoice_is_created_with_number_lines_and_totals(): void
    {
        $this->actingAs($this->user)->post(route('invoices.store'), $this->invoicePayload())->assertSessionHasNoErrors();

        $invoice = Invoice::sole();
        $this->assertSame('SAT-2026-0001', $invoice->number);
        // 18.500 − %10 = 16.650 (+%20 KDV 3.330) ; 2,5 × 349,90 = 874,75 (KDV yok)
        $this->assertSame(1937475, $invoice->subtotal);   // 18.500,00 + 874,75
        $this->assertSame(185000, $invoice->discount_total);
        $this->assertSame(333000, $invoice->vat_total);
        $this->assertSame(2085475, $invoice->total);      // 16.650 + 874,75 + 3.330
        $this->assertCount(2, $invoice->lines);
        $this->assertSame('2.500', $invoice->lines[1]->quantity);
    }

    public function test_numbers_are_sequential_per_type_and_restart_each_year(): void
    {
        $this->actingAs($this->user);
        $supplier = Contact::factory()->supplier()->create();

        $this->post(route('invoices.store'), $this->invoicePayload());
        $this->post(route('invoices.store'), $this->invoicePayload());
        $this->post(route('invoices.store'), $this->invoicePayload(['type' => 'purchase', 'contact_id' => $supplier->id]));
        $this->post(route('invoices.store'), $this->invoicePayload(['issue_date' => '2027-01-02', 'due_date' => '2027-02-01']));

        $this->assertSame(
            ['SAT-2026-0001', 'SAT-2026-0002', 'ALS-2026-0001', 'SAT-2027-0001'],
            Invoice::orderBy('id')->pluck('number')->all(),
        );
    }

    public function test_invalid_lines_are_rejected(): void
    {
        $this->actingAs($this->user)->post(route('invoices.store'), $this->invoicePayload([
            'lines' => [['description' => '', 'quantity' => '0', 'unit_price' => '12,345', 'discount_rate' => 150, 'vat_rate' => 18]],
        ]))->assertSessionHasErrors([
            'lines.0.description', 'lines.0.quantity', 'lines.0.unit_price', 'lines.0.discount_rate', 'lines.0.vat_rate',
        ]);

        $this->post(route('invoices.store'), $this->invoicePayload(['lines' => []]))->assertSessionHasErrors('lines');
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_payments_reduce_the_remaining_amount_and_cannot_exceed_it(): void
    {
        $invoice = Invoice::factory()->create(['contact_id' => $this->customer->id]); // 1.200,00 ₺
        $this->actingAs($this->user);

        $this->post(route('invoices.payments.store', $invoice), ['amount' => '500', 'paid_at' => today()->toDateString(), 'method' => 'cash'])
            ->assertSessionHasNoErrors();
        $this->assertSame(70000, $invoice->fresh()->remaining);
        $this->assertSame('partial', $invoice->fresh()->status);

        $this->post(route('invoices.payments.store', $invoice), ['amount' => '700,01', 'paid_at' => today()->toDateString(), 'method' => 'cash'])
            ->assertSessionHasErrors('amount');

        $this->post(route('invoices.payments.store', $invoice), ['amount' => '700', 'paid_at' => today()->toDateString(), 'method' => 'bank_transfer']);
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertSame(0, $invoice->fresh()->remaining);
    }

    public function test_future_dated_payments_are_rejected(): void
    {
        $invoice = Invoice::factory()->create();

        $this->actingAs($this->user)
            ->post(route('invoices.payments.store', $invoice), ['amount' => '10', 'paid_at' => today()->addDay()->toDateString(), 'method' => 'cash'])
            ->assertSessionHasErrors('paid_at');
    }

    public function test_status_is_derived_from_payments_and_due_date(): void
    {
        $this->assertSame('open', Invoice::factory()->create()->status);
        $this->assertSame('overdue', Invoice::factory()->overdue()->create()->status);
        $this->assertSame('paid', Invoice::factory()->overdue()->create(['paid_total' => 120000])->status);
    }

    public function test_ledger_shows_running_balance(): void
    {
        $a = Invoice::factory()->create(['contact_id' => $this->customer->id, 'issue_date' => '2026-01-05']);
        Invoice::factory()->create(['contact_id' => $this->customer->id, 'issue_date' => '2026-02-05']);
        (new RecordPayment)->handle($a, 120000, '2026-01-20', 'cash', $this->user);

        $this->actingAs($this->user)->get(route('contacts.show', $this->customer))
            ->assertInertia(fn (Assert $page) => $page
                ->component('contacts/show')
                ->where('balance', 120000)
                ->where('entries.0.balance', 120000)
                ->where('entries.1.balance', 0)
                ->where('entries.2.balance', 120000));
    }

    public function test_contact_list_shows_balances(): void
    {
        Invoice::factory()->create(['contact_id' => $this->customer->id, 'paid_total' => 20000]);

        $this->actingAs($this->user)->get(route('contacts.index'))
            ->assertInertia(fn (Assert $page) => $page->where('contacts.data.0.balance', 100000));
    }

    public function test_dashboard_and_reports_summarise_outstanding_amounts(): void
    {
        Invoice::factory()->create(['paid_total' => 20000]);                 // 1.000 kalan
        Invoice::factory()->overdue(45)->create();                          // 1.200 gecikmiş, 31-60 gün
        Invoice::factory()->create(['type' => 'purchase', 'number' => 'ALS-1']); // 1.200 borç

        $this->actingAs($this->user)->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('summary.receivable', 220000)
                ->where('summary.payable', 120000)
                ->where('summary.overdue_count', 1)
                ->has('monthly', 6));

        $this->get(route('reports'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('aging.31-60', 120000)
                ->where('aging.1-30', 0)
                ->has('overdue', 1));
    }

    public function test_csv_export_uses_turkish_excel_format(): void
    {
        Invoice::factory()->create(['number' => 'SAT-2026-0042', 'contact_id' => Contact::factory()->create(['name' => 'Çağrı Şık'])]);

        $csv = $this->actingAs($this->user)->get(route('export.invoices'))->assertOk()->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('SAT-2026-0042;Satış;"Çağrı Şık"', $csv);
        $this->assertStringContainsString(';1200,00;', $csv);
    }

    public function test_pdf_can_be_downloaded(): void
    {
        $invoice = Invoice::factory()->create();

        $this->actingAs($this->user)->get(route('invoices.pdf', $invoice))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }
}
