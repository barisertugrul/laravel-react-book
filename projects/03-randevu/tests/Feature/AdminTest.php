<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;
use App\Models\WorkingHour;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_calendar_shows_the_requested_week(): void
    {
        Appointment::factory()->at(now()->toImmutable()->startOfWeek()->setTime(10, 0))->create();

        $this->actingAs(User::factory()->create())
            ->get(route('calendar'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('calendar')
                ->has('appointments', 1));
    }

    public function test_status_can_be_changed(): void
    {
        $appointment = Appointment::factory()->create();

        $this->actingAs(User::factory()->create())
            ->patch(route('appointments.status', $appointment), ['status' => 'completed']);

        $this->assertSame(AppointmentStatus::Completed, $appointment->fresh()->status);
    }

    public function test_closing_time_must_be_after_opening_time(): void
    {
        $hours = collect(range(1, 7))->map(fn ($d) => [
            'weekday' => $d, 'is_open' => true, 'opens_at' => '09:00', 'closes_at' => '18:00',
        ])->all();
        $hours[2]['closes_at'] = '08:00';

        $this->actingAs(User::factory()->create())
            ->put(route('working-hours.update'), ['hours' => $hours])
            ->assertSessionHasErrors('hours.2.closes_at');

        $this->assertSame(0, WorkingHour::count());
    }
}
