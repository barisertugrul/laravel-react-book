<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use Carbon\CarbonImmutable;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\URL;

/**
 * @property int $id
 * @property int $service_id
 * @property string $customer_name
 * @property string $customer_email
 * @property string $customer_phone
 * @property string|null $note
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property AppointmentStatus $status
 * @property CarbonImmutable|null $reminder_sent_at
 */
#[Fillable(['service_id', 'customer_name', 'customer_email', 'customer_phone', 'note', 'starts_at', 'ends_at', 'status', 'reminder_sent_at'])]
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'reminder_sent_at' => 'immutable_datetime',
            'status' => AppointmentStatus::class,
        ];
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** İptal edilmemiş, yani saati dolu gösteren randevular. */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', '!=', AppointmentStatus::Cancelled);
    }

    /**
     * Verilen zaman aralığıyla çakışan randevular.
     * İki aralık, biri diğeri bitmeden başlıyorsa çakışır:
     *   A.başlangıç < B.bitiş  VE  B.başlangıç < A.bitiş
     */
    public function scopeOverlapping(Builder $query, CarbonImmutable $start, CarbonImmutable $end): void
    {
        $query->where('starts_at', '<', $end)->where('ends_at', '>', $start);
    }

    /** Bildirimler (Notification) müşterinin e-posta adresine gider. */
    public function routeNotificationForMail(): string
    {
        return $this->customer_email;
    }

    /**
     * Müşterinin giriş yapmadan randevusunu iptal edebileceği imzalı bağlantı.
     * Bağlantı randevu saatine kadar geçerlidir; adres değiştirilirse imza bozulur.
     */
    public function cancelUrl(): string
    {
        return URL::temporarySignedRoute('booking.cancel', $this->starts_at, ['appointment' => $this->id]);
    }
}
