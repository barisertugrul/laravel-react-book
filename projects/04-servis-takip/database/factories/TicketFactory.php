<?php

namespace Database\Factories;

use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Ticket> */
class TicketFactory extends Factory
{
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'device_type' => 'Telefon',
            'brand' => 'Samsung',
            'model' => 'Galaxy A54',
            'problem' => 'Ekran kırık',
            'estimated_cost' => 2500,
            'status' => TicketStatus::Received,
        ];
    }

    public function status(TicketStatus $status): static
    {
        return $this->state(['status' => $status]);
    }

    public function assignedTo(User $user): static
    {
        return $this->state(['assigned_to' => $user->id]);
    }
}
