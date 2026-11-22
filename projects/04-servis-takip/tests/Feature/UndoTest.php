<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\TicketStatusChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class UndoTest extends TestCase
{
    use RefreshDatabase;

    private User $tech;

    private Ticket $ticket;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        $this->tech = User::factory()->create(['role' => Role::Technician]);
        $this->ticket = Ticket::factory()->assignedTo($this->tech)->status(TicketStatus::Repairing)->create();

        // Teknisyen yanlışlıkla "teslime hazır" işaretliyor
        $this->actingAs($this->tech)->post(route('tickets.status', $this->ticket), [
            'status' => 'ready',
            'final_cost' => '1000',
        ]);
    }

    public function test_technician_can_undo_own_change_within_the_window(): void
    {
        $this->travel(10)->minutes();

        $this->actingAs($this->tech)->post(route('tickets.undo', $this->ticket))->assertSessionHasNoErrors();

        $this->assertSame(TicketStatus::Repairing, $this->ticket->fresh()->status);
    }

    public function test_history_is_kept_and_the_revert_is_recorded(): void
    {
        $this->actingAs($this->tech)->post(route('tickets.undo', $this->ticket));

        $events = $this->ticket->events()->get();
        $this->assertCount(2, $events);                            // değişiklik + geri alma; hiçbiri silinmedi
        $this->assertSame($events[1]->id, $events[0]->reverts_event_id);
        $this->assertSame(TicketStatus::Repairing, $events[0]->to_status);
    }

    public function test_window_expires_for_technicians_but_not_for_admins(): void
    {
        $this->travel(16)->minutes();

        $this->actingAs($this->tech)->post(route('tickets.undo', $this->ticket))->assertForbidden();

        $admin = User::factory()->create(['role' => Role::Admin]);
        $this->actingAs($admin)->post(route('tickets.undo', $this->ticket));
        $this->assertSame(TicketStatus::Repairing, $this->ticket->fresh()->status);
    }

    public function test_another_technician_cannot_undo(): void
    {
        $other = User::factory()->create(['role' => Role::Technician]);

        $this->actingAs($other)->post(route('tickets.undo', $this->ticket))->assertForbidden();
    }

    public function test_an_undo_cannot_be_undone(): void
    {
        $this->actingAs($this->tech)->post(route('tickets.undo', $this->ticket));
        $this->actingAs($this->tech)->post(route('tickets.undo', $this->ticket))->assertSessionHasErrors('undo');

        $this->assertSame(TicketStatus::Repairing, $this->ticket->fresh()->status);
    }

    public function test_customer_email_is_delayed_and_dropped_after_undo(): void
    {
        $notification = null;
        Notification::assertSentTo($this->ticket->customer, TicketStatusChanged::class, function ($sent) use (&$notification) {
            $notification = $sent;

            return $sent->delay !== null; // hemen değil, gecikmeli gönderilecek
        });

        $this->assertTrue($notification->shouldSend($this->ticket->customer, 'mail'));

        $this->actingAs($this->tech)->post(route('tickets.undo', $this->ticket));

        // Kuyruk işçisi e-postayı göndermeden önce durumu kontrol eder: artık "hazır" değil
        $this->assertFalse($notification->shouldSend($this->ticket->customer, 'mail'));
    }

    public function test_customer_does_not_see_reverted_changes(): void
    {
        $this->actingAs($this->tech)->post(route('tickets.undo', $this->ticket));

        $this->get(route('tracking.show', $this->ticket->tracking_code))
            ->assertInertia(fn ($page) => $page->has('events', 0)); // "hazır" ve geri alması gizli
    }

    public function test_sending_back_to_repair_is_allowed_but_needs_a_reason(): void
    {
        $this->actingAs($this->tech)->post(route('tickets.status', $this->ticket), ['status' => 'repairing'])
            ->assertSessionHasErrors(['note' => 'Bu durum değişikliği için bir gerekçe yazın.']);

        $this->actingAs($this->tech)->post(route('tickets.status', $this->ticket), [
            'status' => 'repairing',
            'note' => 'Son testte şarj sorunu tekrarladı.',
        ])->assertSessionHasNoErrors();

        $this->assertSame(TicketStatus::Repairing, $this->ticket->fresh()->status);
    }
}
