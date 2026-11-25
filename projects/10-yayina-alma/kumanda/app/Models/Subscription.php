<?php

namespace App\Models;

use App\Billing\Plan;
use App\Enums\SubscriptionStatus;
use App\Tenancy\BelongsToAgency;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $plan
 * @property SubscriptionStatus $status
 * @property string $provider
 * @property string $card_token
 * @property string|null $card_last4
 * @property Carbon $current_period_ends_at
 * @property Carbon|null $grace_ends_at
 * @property Carbon|null $last_attempt_at
 * @property Carbon|null $canceled_at
 */
#[Fillable(['plan', 'status', 'provider', 'card_token', 'card_last4', 'current_period_ends_at'])]
#[Hidden(['card_token'])]
class Subscription extends Model
{
    use BelongsToAgency;

    protected function casts(): array
    {
        return [
            'status' => SubscriptionStatus::class,
            'card_token' => 'encrypted', // jeton kart numarası değildir ama çalınırsa kart çekilebilir
            'current_period_ends_at' => 'datetime',
            'grace_ends_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    /** @return HasMany<SubscriptionPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(SubscriptionPayment::class);
    }

    public function plan(): Plan
    {
        return Plan::find($this->plan);
    }

    /**
     * Ajans şu an ücretli planın haklarına sahip mi?
     * Dönem bitmiş ama yenileme henüz denenmemiş olabilir (zamanlayıcı saatte bir çalışır):
     * o kısa arada da plan sürer; karar yenileme denemesinde verilir.
     */
    public function grantsAccess(): bool
    {
        return match ($this->status) {
            SubscriptionStatus::Active => true,
            SubscriptionStatus::PastDue => $this->grace_ends_at !== null && $this->grace_ends_at->isFuture(),
            SubscriptionStatus::Ended => false,
        };
    }

    /** Yenileme zamanı geldi mi? (zamanlayıcının sorgusuyla aynı kural, tek yerde) */
    public function isDue(): bool
    {
        return match ($this->status) {
            SubscriptionStatus::Active => $this->current_period_ends_at->isPast(),
            // Ek sürede günde bir deneme; ek süre bittiyse son karar için de çalışır
            SubscriptionStatus::PastDue => $this->last_attempt_at === null || $this->last_attempt_at->lte(now()->subDay())
                || ($this->grace_ends_at?->isPast() ?? true),
            SubscriptionStatus::Ended => false,
        };
    }
}
