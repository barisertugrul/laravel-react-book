<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Models\Order;

class CancelOrder
{
    /** Ödemesi reddedilen siparişi iptal eder. Yalnızca bekleyen sipariş iptal edilir. */
    public function handle(Order $order): void
    {
        Order::whereKey($order->id)
            ->where('status', OrderStatus::Pending)
            ->update(['status' => OrderStatus::Cancelled]);
    }
}
