<?php

namespace App\Console\Commands;

use App\Actions\CancelOrder;
use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Ödeme süresi dolan siparişleri iptal eder ve ayrılan stoğu geri bırakır.
 * routes/console.php'de her dakika çalışacak şekilde zamanlanmıştır.
 */
#[Signature('orders:expire')]
#[Description('Süresi dolan ödenmemiş siparişleri iptal eder')]
class ExpireOrders extends Command
{
    public function handle(CancelOrder $cancel): int
    {
        $count = 0;

        Order::where('status', OrderStatus::Pending)
            ->where('expires_at', '<', now())
            ->each(function (Order $order) use ($cancel, &$count) {
                $cancel->handle($order);
                $count++;
            });

        $this->info("{$count} sipariş iptal edildi.");

        return self::SUCCESS;
    }
}
