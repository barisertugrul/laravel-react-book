<?php

namespace App\Models;

use App\Casts\MoneyCast;
use App\Enums\InvoiceType;
use Carbon\CarbonImmutable;
use Database\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property InvoiceType $type
 * @property string $number
 * @property int $contact_id
 * @property CarbonImmutable $issue_date
 * @property CarbonImmutable $due_date
 * @property int $subtotal
 * @property int $discount_total
 * @property int $vat_total
 * @property int $total
 * @property int $paid_total
 * @property string|null $notes
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $cancel_reason
 */
#[Fillable(['type', 'number', 'contact_id', 'issue_date', 'due_date', 'subtotal', 'discount_total', 'vat_total', 'total', 'paid_total', 'notes', 'cancelled_at', 'cancel_reason'])]
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    protected $appends = ['status', 'remaining'];

    protected function casts(): array
    {
        return [
            'type' => InvoiceType::class,
            'issue_date' => 'immutable_date:Y-m-d',
            'due_date' => 'immutable_date:Y-m-d',
            'subtotal' => MoneyCast::class,
            'discount_total' => MoneyCast::class,
            'vat_total' => MoneyCast::class,
            'total' => MoneyCast::class,
            'paid_total' => MoneyCast::class,
            'cancelled_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Contact, $this> */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /** @return HasMany<InvoiceLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort_order');
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('paid_at');
    }

    public function getRemainingAttribute(): int
    {
        return $this->cancelled_at ? 0 : $this->total - $this->paid_total;
    }

    /** Durum saklanmaz, her seferinde hesaplanır: ödemelerden ve vadeden türetilir. */
    public function getStatusAttribute(): string
    {
        return match (true) {
            $this->cancelled_at !== null => 'cancelled',
            $this->paid_total >= $this->total => 'paid',
            $this->due_date->isBefore(CarbonImmutable::today()) => 'overdue',
            $this->paid_total > 0 => 'partial',
            default => 'open',
        };
    }

    public function isCancelled(): bool
    {
        return $this->cancelled_at !== null;
    }

    /** İptal edilmemiş ve tamamı ödenmemiş faturalar */
    public function scopeOutstanding(Builder $query): void
    {
        $query->whereNull('cancelled_at')->whereColumn('paid_total', '<', 'total');
    }

    public function scopeOverdue(Builder $query): void
    {
        $query->outstanding()->where('due_date', '<', CarbonImmutable::today());
    }
}
