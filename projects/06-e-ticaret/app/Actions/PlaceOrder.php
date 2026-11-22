<?php

namespace App\Actions;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Cart;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PlaceOrder
{
    /**
     * Sepetten "ödeme bekleyen" bir sipariş oluşturur ve stoğu ayırır.
     *
     * @param  array{shipping_name: string, shipping_phone: string, shipping_address: string, shipping_city: string}  $shipping
     */
    public function handle(User $user, Cart $cart, array $shipping, string $provider): Order
    {
        $lines = $cart->lines();

        if ($lines->isEmpty()) {
            throw ValidationException::withMessages(['cart' => 'Sepetiniz boş.']);
        }

        $order = DB::transaction(function () use ($user, $lines, $shipping, $provider) {
            foreach ($lines as $line) {
                // Stoğu "kontrol et, sonra düş" diye iki adımda değil, TEK bir koşullu sorguyla düşüyoruz:
                //   UPDATE products SET stock = stock - 2 WHERE id = 7 AND stock >= 2
                // Aynı anda gelen iki sipariş son ürünü birlikte alamaz (bkz. "Güvenlik incelemesi").
                $reserved = Product::whereKey($line['product']->id)
                    ->where('stock', '>=', $line['quantity'])
                    ->decrement('stock', $line['quantity']);

                if ($reserved === 0) {
                    // İstisna transaction'ı geri alır: önceki satırlarda düşülen stoklar da geri gelir
                    throw ValidationException::withMessages([
                        'cart' => "{$line['product']->name} için yeterli stok kalmadı. Sepetinizi güncelleyin.",
                    ]);
                }
            }

            $totals = Cart::totals($lines->sum('total'));

            $order = Order::create([
                ...$shipping,
                ...$totals,
                'user_id' => $user->id,
                'payment_provider' => $provider,
                'expires_at' => now()->addMinutes(Order::PAYMENT_WINDOW_MINUTES),
            ]);

            // Ürünün o anki adı ve fiyatı siparişe kopyalanır
            $order->items()->createMany($lines->map(fn (array $line) => [
                'product_id' => $line['product']->id,
                'product_name' => $line['product']->name,
                'unit_price' => $line['product']->price,
                'quantity' => $line['quantity'],
                'total' => $line['total'],
            ]));

            return $order;
        });

        $cart->clear();

        return $order;
    }
}
