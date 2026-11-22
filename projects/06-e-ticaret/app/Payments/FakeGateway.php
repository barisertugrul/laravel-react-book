<?php

namespace App\Payments;

use App\Models\Order;
use Illuminate\Support\Facades\URL;

/**
 * Hesap açmadan geliştirme ve test için sahte ödeme sağlayıcısı.
 * Gerçek bir sağlayıcı gibi davranır: kendi ödeme sayfası vardır ve sonucu
 * imzalı bir webhook ile, ayrı bir istek olarak bildirir (bkz. FakeProviderController).
 */
class FakeGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'fake';
    }

    public function start(Order $order): string
    {
        // İmzalı ve süreli adres: sipariş numarasını değiştiren veya süresi geçen adres çalışmaz
        return URL::temporarySignedRoute('fake-provider.show', $order->expires_at, ['order' => $order->number]);
    }

    /** Webhook gövdesini imzalar: zaman damgası + gövde, HMAC-SHA256 */
    public static function sign(string $timestamp, string $body): string
    {
        return hash_hmac('sha256', "{$timestamp}.{$body}", (string) config('shop.fake.secret'));
    }
}
