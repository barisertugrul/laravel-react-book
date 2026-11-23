<?php

namespace App\Payments;

use App\Models\Order;
use Illuminate\Support\Facades\URL;

/**
 * Hesap açmadan geliştirme ve test için sahte ödeme sağlayıcısı (Bölüm 6).
 * Kendi ödeme sayfası vardır; sonucu imzalı bir webhook ile, ayrı bir istek olarak bildirir.
 */
class FakeGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'fake';
    }

    public function start(Order $order): string
    {
        // İmzalı ve 30 dakika geçerli adres: sipariş numarası değiştirilirse çalışmaz
        return URL::temporarySignedRoute('fake-provider.show', now()->addMinutes(30), ['order' => $order->number]);
    }

    /** Webhook gövdesini imzalar: zaman damgası + gövde, HMAC-SHA256 */
    public static function sign(string $timestamp, string $body): string
    {
        return hash_hmac('sha256', "{$timestamp}.{$body}", (string) config('licensing.fake.secret'));
    }
}
