<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Notifications\OrderPaid;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use UnexpectedValueException;

class MarkOrderPaid
{
    /**
     * Ödeme sağlayıcısının DOĞRULANMIŞ bildirimiyle çağrılır; tarayıcıdan gelen bir istekle asla.
     * Aynı bildirim iki kez gelse de sonuç değişmez (idempotent).
     *
     * @param  int  $amount  sağlayıcının bildirdiği tahsil edilen tutar (kuruş)
     */
    public function handle(Order $order, int $amount, string $reference): Order
    {
        $order = DB::transaction(function () use ($order, $amount, $reference) {
            // Satırı kilitle: aynı anda gelen iki bildirim sırayla işlensin
            $order = Order::lockForUpdate()->findOrFail($order->id);

            if (! $order->isPending()) {
                if ($order->status === OrderStatus::Cancelled) {
                    // Süresi dolup stoğu iade edilmiş siparişe ödeme geldi: elle iade gerekir
                    Log::critical('İptal edilmiş siparişe ödeme geldi', ['order' => $order->number, 'reference' => $reference]);
                }

                return $order; // zaten ödenmiş: ikinci bildirim hiçbir şey değiştirmez
            }

            if ($amount !== $order->total) {
                throw new UnexpectedValueException("{$order->number}: beklenen {$order->total}, gelen {$amount} kuruş.");
            }

            $order->update([
                'status' => OrderStatus::Paid,
                'paid_at' => now(),
                'payment_reference' => $reference,
            ]);

            // Bildirim, transaction başarıyla kaydedildikten SONRA kuyruğa girer
            DB::afterCommit(fn () => $order->user->notify(new OrderPaid($order)));

            return $order;
        });

        return $order;
    }
}
