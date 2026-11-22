<?php

namespace Database\Factories;

use App\Enums\ContactType;
use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => ContactType::Customer,
            'name' => fake()->company(),
            'tax_number' => fake()->numerify('##########'),
            'tax_office' => fake()->city(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->numerify('05## ### ## ##'),
            'address' => fake()->address(),
        ];
    }

    public function supplier(): static
    {
        return $this->state(['type' => ContactType::Supplier]);
    }
}
