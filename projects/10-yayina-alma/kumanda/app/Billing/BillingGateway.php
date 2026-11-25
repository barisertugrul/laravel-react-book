<?php

namespace App\Billing;

use App\Models\Agency;
use App\Models\Subscription;

/**
 * Abonelik için ödeme sağlayıcısının uygulamaya bakan yüzü (Bölüm 6'daki PaymentGateway'in kardeşi).
 *
 * Tek seferlik ödemeden farkı: kart sağlayıcıda SAKLANIR, bize yalnızca bir jeton (token) döner.
 * Her ay o jetonla, müşteri ortada yokken tahsilat yapılır.
 */
interface BillingGateway
{
    public function name(): string;

    /**
     * Müşterinin kartını girdiği (ve ilk ödemeyi yaptığı) sağlayıcı sayfası.
     * Sonuç bu adresten değil, sağlayıcının imzalı bildiriminden (webhook) öğrenilir.
     *
     * @param  'subscribe'|'card'  $purpose  yeni abonelik ya da yalnızca kart değişikliği
     */
    public function checkoutUrl(Agency $agency, Plan $plan, string $purpose): string;

    /** Kayıtlı kartla tahsilat (yenileme). Sağlayıcı sonucu hemen döndürür. */
    public function charge(Subscription $subscription, int $amount): ChargeResult;
}
