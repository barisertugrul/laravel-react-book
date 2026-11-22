<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Service;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Appointment> */
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        $start = CarbonImmutable::tomorrow()->setTime(10, 0);

        return [
            'service_id' => Service::factory(),
            'customer_name' => fake()->name(),
            'customer_email' => fake()->safeEmail(),
            'customer_phone' => '+90 532 000 00 00',
            'starts_at' => $start,
            'ends_at' => $start->addMinutes(30),
            'status' => AppointmentStatus::Confirmed,
        ];
    }

    /** Randevuyu verilen saatte, verilen süreyle başlatır. */
    public function at(CarbonImmutable $start, int $minutes = 30): static
    {
        return $this->state(['starts_at' => $start, 'ends_at' => $start->addMinutes($minutes)]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => AppointmentStatus::Cancelled]);
    }
}
