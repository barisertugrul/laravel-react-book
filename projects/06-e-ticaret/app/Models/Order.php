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
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $number
 * @property int $user_id
 * @property OrderStatus $status
 * @property int $subtotal
 * @property int $shipping
 * @property int $total
 * @property string $shipping_name
 * @property string $shipping_phone
 * @property string $shipping_address
 * @property string $shipping_city
 * @property string $payment_provider
 * @property string|null $payment_reference
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $paid_at
 * @property CarbonImmutable|null $shipped_at
 * @property string|null $tracking_number
 */
#[Fillable([
    'user_id', 'status', 'subtotal', 'shipping', 'total',
    'shipping_name', 'shipping_phone', 'shipping_address', 'shipping_city',
    'payment_provider', 'payment_reference', 'expires_at', 'paid_at', 'shipped_at', 'tracking_number',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /** Ödeme için tanınan süre; bu sürede ödenmeyen siparişin stoğu geri bırakılır */
    public const PAYMENT_WINDOW_MINUTES = 30;

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'subtotal' => MoneyCast::class,
            'shipping' => MoneyCast::class,
            'total' => MoneyCast::class,
            'expires_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
            'shipped_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        // Numara, veritabanının verdiği id'den türetilir: aynı numara iki kez çıkamaz
        static::created(function (Order $order) {
            $order->number = sprintf('SIP-%06d', $order->id);
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

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function isPending(): bool
    {
        return $this->status === OrderStatus::Pending;
    }
}
