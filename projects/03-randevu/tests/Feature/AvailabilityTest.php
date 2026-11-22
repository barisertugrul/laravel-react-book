<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\WorkingHour;
use App\Support\Availability;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private Service $service;

    private CarbonImmutable $monday;

    protected function setUp(): void
    {
        parent::setUp();

        // Zamana bağlı testler "şimdi"yi sabitlemeli; yoksa yarın bozulurlar.
        $this->monday = CarbonImmutable::parse('2026-11-23');
        $this->travelTo($this->monday->setTime(8, 0));

        WorkingHour::create(['weekday' => 1, 'is_open' => true, 'opens_at' => '09:00', 'closes_at' => '12:00']);
        $this->service = Service::factory()->create(['duration' => 30]);
    }

    private function slots(?CarbonImmutable $date = null): array
    {
        return app(Availability::class)->slots($this->service, $date ?? $this->monday);
    }

    public function test_slots_fill_the_working_hours(): void
    {
        $slots = $this->slots();

        $this->assertSame('09:00', $slots[0]);
        $this->assertSame('11:30', end($slots)); // 11:30 + 30 dk = 12:00, kapanışa sığar
        $this->assertCount(11, $slots);
    }

    public function test_overlapping_times_are_excluded(): void
    {
        Appointment::factory()->for($this->service)->at($this->monday->setTime(10, 0))->create();

        $slots = $this->slots();

        $this->assertContains('09:30', $slots);    // 09:30-10:00 → tam sınırda biter, çakışmaz
        $this->assertNotContains('09:45', $slots); // 09:45-10:15 → çakışır
        $this->assertNotContains('10:00', $slots);
        $this->assertNotContains('10:15', $slots);
        $this->assertContains('10:30', $slots);    // 10:30'da başlar, çakışmaz
    }

    public function test_cancelled_appointments_free_the_time(): void
    {
        Appointment::factory()->for($this->service)->at($this->monday->setTime(10, 0))->cancelled()->create();

        $this->assertContains('10:00', $this->slots());
    }

    public function test_closed_days_have_no_slots(): void
    {
        // Salı için çalışma saati tanımlı değil
        $this->assertSame([], $this->slots($this->monday->addDay()));
    }

    public function test_minimum_notice_is_respected(): void
    {
        $this->travelTo($this->monday->setTime(9, 20));

        // 09:20 + 60 dk = 10:20 → ilk uygun adım 10:30
        $this->assertSame('10:30', $this->slots()[0]);
    }

    public function test_dates_outside_the_booking_window_are_rejected(): void
    {
        $this->assertSame([], $this->slots($this->monday->addWeeks(3)));
    }
}
