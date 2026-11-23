<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\OrderStatus;
use Carbon\CarbonImmutable;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $number
 * @property int $user_id
 * @property int $plan_id
 * @property OrderStatus $status
 * @property int $total
 * @property string $payment_provider
 * @property string|null $payment_reference
 * @property CarbonImmutable|null $paid_at
 * @property-read User $user
 * @property-read Plan $plan
 * @property-read License|null $license
 */
#[Fillable(['user_id', 'plan_id', 'status', 'total', 'payment_provider', 'payment_reference', 'paid_at'])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'total' => MoneyCast::class,
            'paid_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        // Numara, veritabanının verdiği id'den türetilir: aynı numara iki kez çıkamaz (Bölüm 6)
        static::created(function (Order $order) {
            $order->number = sprintf('LIS-%06d', $order->id);
            $order->saveQuietly();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'number';
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

    /** @return HasOne<License, $this> */
    public function license(): HasOne
    {
        return $this->hasOne(License::class);
    }

    public function isPending(): bool
    {
        return $this->status === OrderStatus::Pending;
    }
}
