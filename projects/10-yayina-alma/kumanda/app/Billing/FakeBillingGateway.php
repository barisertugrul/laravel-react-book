<?php

namespace App\Billing;

use App\Models\Agency;
use App\Models\Subscription;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * Hesap açmadan geliştirme ve test için sahte abonelik sağlayıcısı.
 *
 * Test kartları (gerçek sağlayıcıların test kartlarına benzer):
 *   4242  her ödeme başarılı
 *   0341  kart kaydedilir ve ilk ödeme geçer, YENİLEMELER reddedilir (ek süre akışını denemek için)
 *   0002  her ödeme reddedilir
 */
class FakeBillingGateway implements BillingGateway
{
    public const CARDS = ['4242', '0341', '0002'];

    /** Sahte sağlayıcı seçili ve kullanılabilir mi? Canlıda yalnızca BILLING_ALLOW_FAKE=true ile. */
    public static function allowed(): bool
    {
        return config('billing.provider') === 'fake'
            && (! app()->isProduction() || config('billing.allow_fake'));
    }

    /** Canlıda bilerek açılmış demo mu? (arayüzde uyarı şeridi için) */
    public static function isDemo(): bool
    {
        return self::allowed() && app()->isProduction();
    }

    public function name(): string
    {
        return 'fake';
    }

    public function checkoutUrl(Agency $agency, Plan $plan, string $purpose): string
    {
        // İmzalı ve süreli: planı ya da ajansı adresten değiştiren kişi imzayı bozar
        return URL::temporarySignedRoute('fake-billing.show', now()->addMinutes(30), [
            'agency' => $agency->id,
            'plan' => $plan->key,
            'purpose' => $purpose,
        ]);
    }

    public function charge(Subscription $subscription, int $amount): ChargeResult
    {
        return match ($subscription->card_last4) {
            '4242' => ChargeResult::paid('FAKE-'.Str::upper(Str::random(10))),
            '0341' => ChargeResult::declined('Kart reddedildi: yetersiz bakiye.'),
            default => ChargeResult::declined('Kart reddedildi.'),
        };
    }

    /** Sahte sağlayıcının kart jetonu: gerçekte sağlayıcının kendi kimliğidir, kart numarası değildir */
    public static function token(string $last4): string
    {
        return "tok_fake_{$last4}_".Str::random(16);
    }

    /** Webhook gövdesini imzalar: zaman damgası + gövde, HMAC-SHA256 (Bölüm 6) */
    public static function sign(string $timestamp, string $body): string
    {
        return hash_hmac('sha256', "{$timestamp}.{$body}", (string) config('billing.fake.secret'));
    }
}
