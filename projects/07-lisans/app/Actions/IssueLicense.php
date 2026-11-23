<?php

namespace App\Actions;

use App\Enums\LicenseEventType;
use App\Models\License;
use App\Models\Order;
use App\Notifications\LicenseIssued;
use App\Support\LicenseKey;
use Illuminate\Support\Facades\DB;
use LogicException;

class IssueLicense
{
    /**
     * Ödenmiş bir sipariş için lisans üretir. Tekrar çağrılırsa mevcut lisansı döndürür (idempotent):
     * kuyruk bir işi iki kez çalıştırabilir, webhook iki kez gelebilir.
     */
    public function handle(Order $order): License
    {
        if ($order->fresh()->isPending()) {
            throw new LogicException("{$order->number} ödenmeden lisans üretilemez.");
        }

        // 1. savunma: zaten üretildiyse aynısını ver
        if ($existing = License::where('order_id', $order->id)->first()) {
            return $existing;
        }

        // 2. savunma: iki iş AYNI ANDA buraya gelirse licenses.order_id üzerindeki unique dizin
        // ikincisini hatayla durdurur; kuyruk işi tekrar dener ve 1. savunmaya takılır.
        return DB::transaction(function () use ($order) {
            $plan = $order->plan;

            $license = License::create([
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'plan_id' => $plan->id,
                'max_sites' => $plan->max_sites,
                'updates_until' => now()->addMonths(config('licensing.update_months')),
            ]);

            // Anahtar lisansın id'sini içerir; bu yüzden kayıttan sonra üretilir
            $license->update(['key' => LicenseKey::issue($license)]);
            $license->log(LicenseEventType::Issued, meta: ['order' => $order->number]);

            DB::afterCommit(fn () => $order->user->notify(new LicenseIssued($license)));

            return $license;
        });
    }
}
