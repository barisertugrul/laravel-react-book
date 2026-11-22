<?php

namespace Tests\Feature;

use App\Actions\RecordPayment;
use App\Models\Invoice;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bölüm sonundaki "Güvenlik incelemesi"nde anlatılan iki hatanın testleri.
 */
class SecurityFixesTest extends TestCase
{
    use RefreshDatabase;

    /** 1. hata: tutarı float ile kuruşa çevirmek */
    public function test_float_conversion_loses_cents_but_money_parse_does_not(): void
    {
        // İlk sürümdeki kod: (int) ($value * 100)
        $old = fn (string $value) => (int) ((float) $value * 100);

        $this->assertSame(1998, $old('19.99'));   // bir kuruş kayıp!
        $this->assertSame(28, $old('0.29'));      // yine!
        $this->assertSame(1999, Money::parse('19.99'));
        $this->assertSame(29, Money::parse('0,29'));
    }

    /** 1. hata (devamı): float toplamlar da kayar */
    public function test_summing_float_amounts_drifts_but_integer_cents_do_not(): void
    {
        // On satır, her biri 0,10 ₺ KDV: ilk sürüm float topluyordu
        $floatTotal = 0.0;
        $centsTotal = 0;
        foreach (range(1, 10) as $_) {
            $floatTotal += 0.10;
            $centsTotal += 10;
        }

        $this->assertNotSame(1.0, $floatTotal);           // 0.9999999999999999
        $this->assertSame(99, (int) ($floatTotal * 100)); // veritabanına 0,99 ₺ yazılırdı
        $this->assertSame(100, $centsTotal);
        $this->assertSame('1,00 ₺', Money::format($centsTotal));
    }

    /** 2. hata: fatura silinince ödemeleri de sessizce silinmesi */
    public function test_invoices_cannot_be_deleted_through_the_app(): void
    {
        $invoice = Invoice::factory()->create();

        $this->actingAs(User::factory()->create())
            ->delete("/invoices/{$invoice->id}")
            ->assertMethodNotAllowed();

        $this->assertModelExists($invoice);
    }

    public function test_database_refuses_to_delete_an_invoice_with_payments(): void
    {
        $invoice = Invoice::factory()->create();
        (new RecordPayment)->handle($invoice, 50000, today()->toDateString(), 'cash', User::factory()->create());

        $this->expectException(QueryException::class);
        $invoice->delete();
    }

    public function test_invoice_is_cancelled_instead_and_leaves_balances(): void
    {
        $invoice = Invoice::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('invoices.cancel', $invoice), ['reason' => 'Mükerrer kayıt'])->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertSame('cancelled', $invoice->status);
        $this->assertSame(0, $invoice->remaining);
        $this->assertSame(0, $invoice->contact->balance());

        // İptal edilmiş faturaya ödeme girilemez
        $this->post(route('invoices.payments.store', $invoice), ['amount' => '10', 'paid_at' => today()->toDateString(), 'method' => 'cash'])
            ->assertSessionHasErrors('amount');
    }

    public function test_paid_invoice_cannot_be_cancelled(): void
    {
        $invoice = Invoice::factory()->create();
        $user = User::factory()->create();
        (new RecordPayment)->handle($invoice, 1000, today()->toDateString(), 'cash', $user);

        $this->actingAs($user)->post(route('invoices.cancel', $invoice), ['reason' => 'Vazgeçildi'])->assertSessionHasErrors('reason');
        $this->assertNull($invoice->fresh()->cancelled_at);
    }
}
