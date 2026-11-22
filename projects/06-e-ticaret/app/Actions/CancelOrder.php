<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

/**
 * Ödenmemiş bir siparişi iptal eder ve ayrılan stoğu geri bırakır.
 * Ödeme reddedildiğinde ve ödeme süresi dolduğunda çağrılır.
 */
class CancelOrder
{
    public function handle(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = Order::lockForUpdate()->findOrFail($order->id);

            // Yalnızca ödeme bekleyen sipariş iptal edilir; aksi halde stok iki kez iade edilirdi
            if (! $order->isPending()) {
                return $order;
            }

            foreach ($order->items as $item) {
                if ($item->product_id) {
                    Product::whereKey($item->product_id)->increment('stock', $item->quantity);
                }
            }

            $order->update(['status' => OrderStatus::Cancelled]);

            return $order;
        });
    }
}
