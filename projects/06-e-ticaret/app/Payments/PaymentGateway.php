<?php

namespace App\Payments;

use App\Models\Order;

/**
 * Bir ödeme sağlayıcısının uygulamamıza bakan yüzü.
 * Uygulama yalnızca bu arayüzü bilir; hangi sağlayıcının kullanılacağı config/shop.php'den seçilir.
 */
interface PaymentGateway
{
    /** config'deki ve siparişte saklanan ad: "fake", "iyzico" */
    public function name(): string;

    /**
     * Ödemeyi başlatır ve müşterinin yönlendirileceği ödeme sayfasının adresini döndürür.
     * Bu adım siparişi ÖDENMİŞ yapmaz; onay, sağlayıcıdan ayrıca ve doğrulanarak gelir.
     */
    public function start(Order $order): string;
}
