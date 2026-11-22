<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TicketTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $tech;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->admin = User::factory()->create(['role' => Role::Admin]);
        $this->tech = User::factory()->create(['role' => Role::Technician]);
    }

    public function test_ticket_gets_a_readable_code_and_secret_tracking_code(): void
    {
        $ticket = Ticket::factory()->create();

        $this->assertSame(sprintf('SRV-%06d', $ticket->id), $ticket->code);
        $this->assertSame(26, strlen($ticket->tracking_code));
    }

    public function test_returning_customer_is_found_by_phone(): void
    {
        $customer = Customer::factory()->create(['phone' => '5321234567']);

        $this->actingAs($this->tech)->post(route('tickets.store'), [
            'customer_name' => 'Ayşe Demir',
            'customer_phone' => '0532 123 45 67', // farklı yazım, aynı numara
            'device_type' => 'Telefon',
            'brand' => 'Apple',
            'problem' => 'Ekran kırık',
        ])->assertRedirect();

        $this->assertSame(1, Customer::count());
        $this->assertSame($customer->id, Ticket::sole()->customer_id);
        $this->assertSame(TicketStatus::Received, Ticket::sole()->events()->sole()->to_status);
    }

    public function test_status_change_is_logged_and_customer_is_notified(): void
    {
        $ticket = Ticket::factory()->assignedTo($this->tech)->status(TicketStatus::Repairing)->create();

        $this->actingAs($this->tech)->post(route('tickets.status', $ticket), [
            'status' => 'ready',
            'final_cost' => '2750',
            'note' => 'Ekran değiştirildi.',
        ])->assertSessionHasNoErrors();

        $ticket->refresh();
        $this->assertSame(TicketStatus::Ready, $ticket->status);
        $this->assertSame('2750.00', $ticket->final_cost);
        $this->assertSame(TicketStatus::Repairing, $ticket->events()->first()->from_status);
        Notification::assertSentTo($ticket->customer, TicketStatusChanged::class);
    }

    public function test_invalid_transition_is_rejected(): void
    {
        $ticket = Ticket::factory()->assignedTo($this->tech)->create(); // received

        $this->actingAs($this->tech)->post(route('tickets.status', $ticket), ['status' => 'delivered'])
            ->assertSessionHasErrors('status');

        $this->assertSame(TicketStatus::Received, $ticket->fresh()->status);
    }

    public function test_ready_requires_a_final_cost(): void
    {
        $ticket = Ticket::factory()->assignedTo($this->tech)->status(TicketStatus::Repairing)->create();

        $this->actingAs($this->tech)->post(route('tickets.status', $ticket), ['status' => 'ready'])
            ->assertSessionHasErrors('final_cost');
    }

    public function test_technician_sees_only_own_and_unassigned_tickets(): void
    {
        $other = User::factory()->create(['role' => Role::Technician]);
        $mine = Ticket::factory()->assignedTo($this->tech)->create();
        Ticket::factory()->create(); // atanmamış
        $theirs = Ticket::factory()->assignedTo($other)->create();

        $this->actingAs($this->tech)->get(route('tickets.index'))
            ->assertInertia(fn (Assert $page) => $page->has('tickets.data', 2));

        $this->actingAs($this->tech)->get(route('tickets.show', $mine))->assertOk();
        $this->actingAs($this->tech)->get(route('tickets.show', $theirs))->assertForbidden();
        $this->actingAs($this->admin)->get(route('tickets.show', $theirs))->assertOk();
    }

    public function test_unassigned_ticket_is_claimed_by_first_technician_who_moves_it(): void
    {
        $ticket = Ticket::factory()->create();

        $this->actingAs($this->tech)->post(route('tickets.status', $ticket), ['status' => 'diagnosing']);

        $this->assertSame($this->tech->id, $ticket->fresh()->assigned_to);
    }

    public function test_only_admin_can_assign(): void
    {
        $ticket = Ticket::factory()->create();

        $this->actingAs($this->tech)->patch(route('tickets.assign', $ticket), ['assigned_to' => $this->tech->id])
            ->assertForbidden();

        $this->actingAs($this->admin)->patch(route('tickets.assign', $ticket), ['assigned_to' => $this->tech->id]);
        $this->assertSame($this->tech->id, $ticket->fresh()->assigned_to);
    }

    public function test_team_page_is_admin_only(): void
    {
        $this->actingAs($this->tech)->get(route('team.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('team.index'))->assertOk();
    }

    public function test_filters_and_pagination(): void
    {
        Ticket::factory()->count(12)->create();
        Ticket::factory()->status(TicketStatus::Ready)->create(['brand' => 'Nintendo']);

        $this->actingAs($this->admin)->get(route('tickets.index', ['q' => 'nintendo']))
            ->assertInertia(fn (Assert $page) => $page->has('tickets.data', 1));

        $this->actingAs($this->admin)->get(route('tickets.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('tickets.data', 10)
                ->where('tickets.last_page', 2)
                ->where('counts.ready', 1));
    }

    public function test_receipt_is_a_pdf(): void
    {
        $ticket = Ticket::factory()->create();

        $this->actingAs($this->admin)->get(route('tickets.receipt', $ticket))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }
}
