<?php

namespace Database\Factories;

use App\Enums\CardTheme;
use App\Models\Card;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Card> */
class CardFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->name();

        return [
            'user_id' => User::factory(),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'full_name' => $name,
            'title' => fake()->jobTitle(),
            'company' => fake()->company(),
            'phone' => '+90 532 000 00 00',
            'email' => fake()->safeEmail(),
            'theme' => CardTheme::Klasik,
            'is_published' => true,
        ];
    }

    public function unpublished(): static
    {
        return $this->state(['is_published' => false]);
    }
}
