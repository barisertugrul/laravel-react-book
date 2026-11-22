<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\WorkingHour;
use App\Notifications\AppointmentBooked;
use App\Notifications\AppointmentReminder;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake(); // Gerçek e-posta gönderilmez, yalnızca kaydedilir
        $this->travelTo(CarbonImmutable::parse('2026-11-23 08:00')); // Pazartesi

        foreach (range(1, 7) as $day) {
            WorkingHour::create(['weekday' => $day, 'is_open' => true, 'opens_at' => '09:00', 'closes_at' => '18:00']);
        }
        $this->service = Service::factory()->create(['name' => 'Saç Kesimi', 'duration' => 30]);
    }

    private function book(array $overrides = [])
    {
        return $this->post(route('booking.store'), [
            'service_id' => $this->service->id,
            'date' => '2026-11-23',
            'time' => '10:00',
            'customer_name' => 'Ayşe Demir',
            'customer_email' => 'ayse@ornek.com',
            'customer_phone' => '+90 532 123 45 67',
            ...$overrides,
        ]);
    }

    public function test_booking_page_loads_slots_only_when_requested(): void
    {
        $this->get('/?service='.$this->service->id.'&date=2026-11-23')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('booking/show')
                ->has('services', 1)
                ->missing('slots') // optional prop: ilk yüklemede gönderilmez
                // 09:00-17:30 arası, 15 dakikada bir: 35 saat
                ->reloadOnly('slots', fn (Assert $reload) => $reload
                    ->has('slots', 35)
                    ->where('slots.0', '09:00')));
    }

    public function test_customer_can_book_and_gets_an_email(): void
    {
        $response = $this->book();

        $appointment = Appointment::sole();
        $this->assertSame('2026-11-23 10:30', $appointment->ends_at->format('Y-m-d H:i'));
        $response->assertRedirectContains('/randevu/'.$appointment->id.'/tamam?');

        Notification::assertSentTo($appointment, AppointmentBooked::class);
    }

    public function test_the_same_time_cannot_be_booked_twice(): void
    {
        $this->book();
        $this->book(['customer_name' => 'Mehmet Kaya'])->assertSessionHasErrors('time');

        $this->assertSame(1, Appointment::count());
    }

    public function test_times_outside_working_hours_are_rejected(): void
    {
        $this->book(['time' => '20:00'])->assertSessionHasErrors('time');
    }

    public function test_customer_can_cancel_with_signed_link(): void
    {
        $appointment = Appointment::factory()->for($this->service)->create();
        $url = $appointment->cancelUrl();

        $this->get($url)->assertOk();
        $this->post($url);

        $this->assertSame(AppointmentStatus::Cancelled, $appointment->fresh()->status);
    }

    public function test_tampered_cancel_link_is_rejected(): void
    {
        $appointment = Appointment::factory()->for($this->service)->create();
        $other = Appointment::factory()->for($this->service)->create();

        // Bağlantıdaki randevu numarasını başka bir randevununkiyle değiştirmek imzayı bozar
        $tampered = str_replace('/randevu/'.$appointment->id.'/', '/randevu/'.$other->id.'/', $appointment->cancelUrl());

        $this->post($tampered)->assertForbidden();
        $this->post(URL::route('booking.cancel', $appointment))->assertForbidden(); // imzasız

        $this->assertSame(AppointmentStatus::Confirmed, $other->fresh()->status);
    }

    public function test_reminders_are_sent_once_for_tomorrows_appointments(): void
    {
        $tomorrow = Appointment::factory()->for($this->service)->at(CarbonImmutable::parse('2026-11-24 11:00'))->create();
        $nextWeek = Appointment::factory()->for($this->service)->at(CarbonImmutable::parse('2026-11-30 11:00'))->create();

        $this->artisan('appointments:send-reminders')->assertSuccessful();
        $this->artisan('appointments:send-reminders'); // ikinci kez çalışsa da tekrar göndermemeli

        Notification::assertSentToTimes($tomorrow, AppointmentReminder::class, 1);
        Notification::assertNotSentTo($nextWeek, AppointmentReminder::class);
    }
}
