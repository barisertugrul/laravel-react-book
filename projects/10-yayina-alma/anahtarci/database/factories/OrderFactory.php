<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Ödeme bekleyen, 1.490,00 ₺'lik sipariş (PlanFactory'nin varsayılan fiyatı)
 *
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'plan_id' => Plan::factory(),
            'status' => 'pending',
            'total' => 149000,
            'payment_provider' => 'fake',
        ];
    }
}
