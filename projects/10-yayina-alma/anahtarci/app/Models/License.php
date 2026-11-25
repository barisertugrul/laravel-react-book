<?php

namespace App\Models;

use App\Enums\LicenseEventType;
use App\Enums\LicenseStatus;
use Carbon\CarbonImmutable;
use Database\Factories\LicenseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $order_id
 * @property int $user_id
 * @property int $plan_id
 * @property string $key
 * @property LicenseStatus $status
 * @property int|null $max_sites null: sınırsız
 * @property CarbonImmutable $updates_until
 * @property CarbonImmutable|null $revoked_at
 * @property string|null $revoke_reason
 * @property CarbonImmutable $created_at
 * @property-read User $user
 * @property-read Plan $plan
 * @property-read Order|null $order
 */
#[Fillable(['order_id', 'user_id', 'plan_id', 'key', 'status', 'max_sites', 'updates_until', 'revoked_at', 'revoke_reason'])]
// Anahtar yalnızca sahibine, bilerek gösterilir; modelin JSON'una kendiliğinden girmez
#[Hidden(['key'])]
class License extends Model
{
    /** @use HasFactory<LicenseFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => LicenseStatus::class,
            'max_sites' => 'integer',
            'updates_until' => 'immutable_date',
            'revoked_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Plan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return HasMany<Activation, $this> */
    public function activations(): HasMany
    {
        return $this->hasMany(Activation::class);
    }

    /** @return HasMany<LicenseEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(LicenseEvent::class)->latest('id');
    }

    public function isActive(): bool
    {
        return $this->status === LicenseStatus::Active;
    }

    /** Güncelleme ve destek hakkı sürüyor mu? (Bitmesi lisansı durdurmaz.) */
    public function hasUpdates(): bool
    {
        return $this->updates_until->endOfDay()->isFuture();
    }

    public function isUnlimited(): bool
    {
        return $this->max_sites === null;
    }

    public function log(LicenseEventType $type, ?string $domain = null, ?string $ip = null, array $meta = []): LicenseEvent
    {
        return $this->events()->create([
            'type' => $type,
            'domain' => $domain,
            'ip' => $ip,
            'meta' => $meta ?: null,
            'created_at' => now(),
        ]);
    }

    /**
     * API'nin ve WordPress eklentisinin gördüğü lisans bilgisi.
     * "valid" alanını eklenti kullanır; karar her zaman SUNUCUDAKİ duruma göre verilir.
     *
     * @return array<string, mixed>
     */
    public function toApiArray(): array
    {
        return [
            'valid' => $this->isActive(),
            'status' => $this->status->value,
            'product' => $this->plan->product->slug,
            'product_name' => $this->plan->product->name,
            'plan' => $this->plan->name,
            'max_sites' => $this->max_sites,
            'sites_used' => $this->activations()->count(),
            'updates_until' => $this->updates_until->toDateString(),
            'updates_available' => $this->hasUpdates(),
        ];
    }
}
