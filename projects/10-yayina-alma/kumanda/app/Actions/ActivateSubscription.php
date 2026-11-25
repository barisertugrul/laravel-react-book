<?php

namespace App\Actions;

use App\Billing\Plan;
use App\Enums\SubscriptionStatus;
use App\Models\Agency;
use App\Models\Subscription;
use App\Tenancy\CurrentAgency;
use Illuminate\Support\Facades\DB;

/**
 * Sağlayıcı ilk ödemeyi ve kartı onayladı: aboneliği başlatır (ya da biten aboneliği yeniden başlatır).
 * Webhook'tan çağrılır: oturum ve ajans bağlamı yoktur, bağlamı burada kurarız.
 */
class ActivateSubscription
{
    public function __construct(private CurrentAgency $current) {}

    public function handle(Agency $agency, Plan $plan, string $provider, string $cardToken, string $last4, int $amount, string $reference): Subscription
    {
        return $this->current->run($agency, fn () => DB::transaction(function () use ($agency, $plan, $provider, $cardToken, $last4, $amount, $reference) {
            // Ajans başına tek satır: varsa güncellenir (eski iptal/ek süre bilgileri temizlenir)
            $subscription = Subscription::firstOrNew(['agency_id' => $agency->id]);
            $subscription->forceFill([
                'plan' => $plan->key,
                'status' => SubscriptionStatus::Active,
                'provider' => $provider,
                'card_token' => $cardToken,
                'card_last4' => $last4,
                'current_period_ends_at' => now()->addMonth(),
                'grace_ends_at' => null,
                'last_attempt_at' => now(),
                'canceled_at' => null,
            ])->save();

            $subscription->payments()->create([
                'plan' => $plan->key,
                'amount' => $amount,
                'status' => 'paid',
                'reference' => $reference,
            ]);

            return $subscription;
        }));
    }
}
