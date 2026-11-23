<?php

namespace App\Payments;

use App\Models\Order;

/**
 * Bir ödeme sağlayıcısının uygulamamıza bakan yüzü (Bölüm 6'dan).
 * Uygulama yalnızca bu arayüzü bilir; sağlayıcı config/licensing.php'den seçilir.
 */
interface PaymentGateway
{
    /** config'deki ve siparişte saklanan ad: "fake" */
    public function name(): string;

    /**
     * Ödemeyi başlatır ve müşterinin yönlendirileceği ödeme sayfasının adresini döndürür.
     * Bu adım siparişi ÖDENMİŞ yapmaz; onay, sağlayıcıdan imzalı bir webhook ile gelir.
     */
    public function start(Order $order): string;
}
