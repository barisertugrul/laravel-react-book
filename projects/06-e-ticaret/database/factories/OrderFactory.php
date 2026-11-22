<?php

namespace Database\Factories;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Kalemsiz, 1.000,00 ₺ + kargo bedava bir sipariş. Kalemli sipariş için PlaceOrder kullanın.
 *
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'status' => 'pending',
            'subtotal' => 100000,
            'shipping' => 0,
            'total' => 100000,
            'shipping_name' => fake()->name(),
            'shipping_phone' => '0532 123 45 67',
            'shipping_address' => fake()->streetAddress(),
            'shipping_city' => 'İstanbul',
            'payment_provider' => 'fake',
            'expires_at' => now()->addMinutes(30),
        ];
    }
}
