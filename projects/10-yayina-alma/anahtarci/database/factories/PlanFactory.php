<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Tek siteli, 1.490,00 ₺'lik plan
 *
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'name' => 'Tek site',
            'price' => 149000,
            'max_sites' => 1,
        ];
    }

    public function sites(?int $max): static
    {
        return $this->state(['name' => $max === null ? 'Sınırsız' : "{$max} site", 'max_sites' => $max]);
    }
}
