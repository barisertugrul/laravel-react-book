<?php

namespace App\Actions;

use App\Enums\OrderStatus;
use App\Jobs\IssueLicenseForOrder;
use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use UnexpectedValueException;

class MarkOrderPaid
{
    /**
     * Ödeme sağlayıcısının DOĞRULANMIŞ bildirimiyle çağrılır (Bölüm 6).
     * Aynı bildirim iki kez gelse de sonuç değişmez (idempotent).
     *
     * @param  int  $amount  sağlayıcının bildirdiği tahsil edilen tutar (kuruş)
     */
    public function handle(Order $order, int $amount, string $reference): Order
    {
        return DB::transaction(function () use ($order, $amount, $reference) {
            // Satırı kilitle: aynı anda gelen iki bildirim sırayla işlensin
            $order = Order::lockForUpdate()->findOrFail($order->id);

            if (! $order->isPending()) {
                if ($order->status === OrderStatus::Cancelled) {
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

            // Lisans, kuyrukta ve transaction kaydedildikten SONRA üretilir.
            // Webhook'a hızlıca 200 döneriz; anahtar üretimi veya e-posta takılırsa iş kendi başına tekrar denenir.
            IssueLicenseForOrder::dispatch($order)->afterCommit();

            return $order;
        });
    }
}
