<?php

namespace App\Jobs;

use App\Billing\BillingGateway;
use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use App\Notifications\SubscriptionEnded;
use App\Notifications\SubscriptionPaymentFailed;
use App\Tenancy\CurrentAgency;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

/**
 * Bir aboneliğin dönem sonu: yenile, iptal edildiyse bitir, ek süre dolduysa ücretsiz plana düşür.
 *
 * tries = 1 ve ShouldBeUnique: para çeken bir işin kendiliğinden tekrar denenmesi ya da aynı
 * anda iki kez çalışması çift tahsilat demektir. Başarısız deneme ertesi gün zamanlayıcıyla tekrarlanır.
 */
class RenewSubscription implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $uniqueFor = 3600;

    public function __construct(public Subscription $subscription) {}

    public function uniqueId(): string
    {
        return (string) $this->subscription->id;
    }

    public function handle(CurrentAgency $current, BillingGateway $gateway): void
    {
        $current->run($this->subscription->agency, function () use ($gateway): void {
            $subscription = $this->subscription->fresh();

            // Kuyrukta beklerken durum değişmiş olabilir (ör. müşteri kartını güncelledi ve ödeme geçti)
            if ($subscription === null || ! $subscription->isDue()) {
                return;
            }

            // İptal edildiyse dönem sonunda biter, para çekilmez
            if ($subscription->canceled_at !== null) {
                $this->end($subscription, 'İptal isteğiniz üzerine abonelik dönem sonunda sona erdi.');

                return;
            }

            // Ek süre doldu: son bir deneme daha yok, ajans ücretsiz plana düşer
            if ($subscription->status === SubscriptionStatus::PastDue && $subscription->grace_ends_at?->isPast()) {
                $this->end($subscription, 'Ödeme ek süre içinde alınamadığı için abonelik sona erdi.');

                return;
            }

            $plan = $subscription->plan();
            $result = $gateway->charge($subscription, $plan->price);

            $subscription->payments()->create([
                'plan' => $plan->key,
                'amount' => $plan->price,
                'status' => $result->paid ? 'paid' : 'failed',
                'reference' => $result->reference,
                'failure_message' => $result->message,
            ]);

            if ($result->paid) {
                $subscription->forceFill([
                    'status' => SubscriptionStatus::Active,
                    // Ek süredeyken ödendiyse yeni dönem bugünden başlar (geçmiş günler için ücret alınmaz)
                    'current_period_ends_at' => $subscription->current_period_ends_at->max(now())->addMonth(),
                    'grace_ends_at' => null,
                    'last_attempt_at' => now(),
                ])->save();

                return;
            }

            $firstFailure = $subscription->status !== SubscriptionStatus::PastDue;

            $subscription->forceFill([
                'status' => SubscriptionStatus::PastDue,
                // Ek süre İLK başarısızlıkta başlar; sonraki denemeler süreyi uzatmaz
                'grace_ends_at' => $subscription->grace_ends_at ?? now()->addDays(config('billing.grace_days')),
                'last_attempt_at' => now(),
            ])->save();

            if ($firstFailure) {
                Notification::send($subscription->agency->owners(), new SubscriptionPaymentFailed($subscription, (string) $result->message));
            }
        });
    }

    private function end(Subscription $subscription, string $reason): void
    {
        $subscription->forceFill(['status' => SubscriptionStatus::Ended, 'last_attempt_at' => now()])->save();

        Notification::send($subscription->agency->owners(), new SubscriptionEnded($subscription, $reason));
    }
}
