<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Ticket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_tracking_page_shows_only_public_events(): void
    {
        $ticket = Ticket::factory()->create();
        $ticket->events()->create(['to_status' => 'received', 'note' => 'Teslim alındı', 'is_public' => true]);
        $ticket->events()->create(['to_status' => 'diagnosing', 'note' => 'İç not: müşteri gergin', 'is_public' => false]);

        $this->get(route('tracking.show', $ticket->tracking_code))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('tracking/show')
                ->has('events', 1)
                ->missing('ticket.customer'));
    }

    public function test_lookup_with_code_and_phone_digits(): void
    {
        $ticket = Ticket::factory()->for(Customer::factory()->state(['phone' => '5321234567']))->create();

        $this->post(route('tracking.find'), ['code' => strtolower($ticket->code), 'phone_last4' => '4567'])
            ->assertRedirect($ticket->tracking_url);
    }

    public function test_wrong_phone_gives_the_same_error_as_wrong_code(): void
    {
        $ticket = Ticket::factory()->for(Customer::factory()->state(['phone' => '5321234567']))->create();

        $wrongPhone = $this->post(route('tracking.find'), ['code' => $ticket->code, 'phone_last4' => '0000']);
        $wrongCode = $this->post(route('tracking.find'), ['code' => 'SRV-999999', 'phone_last4' => '4567']);

        $wrongPhone->assertSessionHasErrors(['code' => 'Bu bilgilerle eşleşen bir servis kaydı bulunamadı.']);
        $wrongCode->assertSessionHasErrors(['code' => 'Bu bilgilerle eşleşen bir servis kaydı bulunamadı.']);
    }
}
