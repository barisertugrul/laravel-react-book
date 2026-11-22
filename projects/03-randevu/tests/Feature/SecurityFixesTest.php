<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\User;
use App\Notifications\AppointmentCancelled;
use App\Notifications\AppointmentCancelledByCustomer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Bölüm 3'ün "Güvenlik ve iletişim incelemesi" kısmındaki düzeltmeler */
class SecurityFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_is_closed(): void
    {
        $this->get('/register')->assertNotFound();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_staff_user_can_be_created_from_the_command_line(): void
    {
        $this->artisan('user:create')
            ->expectsQuestion('Ad soyad', 'Salon Sahibi')
            ->expectsQuestion('E-posta', 'sahip@example.com')
            ->expectsQuestion('Şifre (en az 8 karakter)', 'gizli-sifre-123')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', ['email' => 'sahip@example.com']);
    }

    public function test_customer_is_notified_when_business_cancels(): void
    {
        Notification::fake();
        $appointment = Appointment::factory()->create(); // yarın 10:00

        $this->actingAs(User::factory()->create())
            ->patch(route('appointments.status', $appointment), ['status' => 'cancelled']);

        Notification::assertSentTo($appointment, AppointmentCancelled::class);
    }

    public function test_staff_is_notified_when_customer_cancels(): void
    {
        Notification::fake();
        $staff = User::factory()->count(2)->create();
        $appointment = Appointment::factory()->create();

        $this->post($appointment->cancelUrl());

        Notification::assertSentTo($staff, AppointmentCancelledByCustomer::class);
    }
}
