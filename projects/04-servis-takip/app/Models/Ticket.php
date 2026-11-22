<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Database\Factories\TicketFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $code
 * @property string $tracking_code
 * @property int $customer_id
 * @property int|null $assigned_to
 * @property string $device_type
 * @property string $brand
 * @property string|null $model
 * @property string|null $serial_number
 * @property string $problem
 * @property string|null $accessories
 * @property string|null $estimated_cost
 * @property string|null $final_cost
 * @property TicketStatus $status
 */
#[Fillable(['customer_id', 'assigned_to', 'device_type', 'brand', 'model', 'serial_number', 'problem', 'accessories', 'estimated_cost', 'final_cost', 'status', 'code'])]
class Ticket extends Model
{
    /** @use HasFactory<TicketFactory> */
    use HasFactory;

    protected $attributes = ['status' => 'received'];

    protected $appends = ['tracking_url'];

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'estimated_cost' => 'decimal:2',
            'final_cost' => 'decimal:2',
        ];
    }

    /**
     * Model olayları (model events): kayıt oluşturulurken ve oluşturulduktan sonra
     * otomatik çalışan kod. Takip kodunu ve okunabilir servis numarasını burada üretiyoruz.
     */
    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket) {
            $ticket->tracking_code ??= Str::lower(Str::ulid());
        });

        static::created(function (Ticket $ticket) {
            // Numara, veritabanının verdiği id'den üretilir: 42 → SRV-000042
            $ticket->updateQuietly(['code' => sprintf('SRV-%06d', $ticket->id)]);
        });
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<User, $this> */
    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return HasMany<TicketEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(TicketEvent::class)->latest('id');
    }

    /** En son olay: geri alma kontrolleri için */
    public function latestEvent(): ?TicketEvent
    {
        return $this->events()->first();
    }

    /** Teknisyen yalnızca kendisine atananları ve henüz atanmamışları görür. */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->isAdmin()) {
            $query->where(fn ($q) => $q->where('assigned_to', $user->id)->orWhereNull('assigned_to'));
        }
    }

    public function getTrackingUrlAttribute(): string
    {
        return route('tracking.show', $this->tracking_code);
    }

    public function getRouteKeyName(): string
    {
        return 'code'; // panelde /tickets/SRV-000042 gibi okunabilir adresler
    }
}
