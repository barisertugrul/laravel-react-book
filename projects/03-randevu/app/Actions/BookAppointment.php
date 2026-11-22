<?php

namespace App\Actions;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Service;
use App\Notifications\AppointmentBooked;
use App\Support\Availability;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Randevu almanın tüm adımları tek bir sınıfta: kilitle, tekrar kontrol et, kaydet, bildir.
 * Controller yalnızca bu sınıfı çağırır; aynı işi ileride bir API veya komut da yapabilir.
 */
class BookAppointment
{
    public function __construct(private Availability $availability) {}

    /**
     * @param  array{customer_name: string, customer_email: string, customer_phone: string, note?: string|null}  $customer
     */
    public function handle(Service $service, CarbonImmutable $startsAt, array $customer): Appointment
    {
        // Aynı güne gelen randevu istekleri sırayla işlensin diye kısa süreli bir kilit alıyoruz.
        // İki müşteri aynı saniyede aynı saate tıklasa bile yalnızca biri kaydedilir.
        $lock = Cache::lock('booking:'.$startsAt->toDateString(), 10);

        $appointment = $lock->block(5, function () use ($service, $startsAt, $customer) {
            // Müşteri sayfayı açtığından beri saat dolmuş olabilir: sunucuda yeniden kontrol et.
            if (! in_array($startsAt->format('H:i'), $this->availability->slots($service, $startsAt), true)) {
                throw ValidationException::withMessages([
                    'time' => 'Bu saat az önce doldu. Lütfen başka bir saat seçin.',
                ]);
            }

            return DB::transaction(fn () => Appointment::create([
                ...$customer,
                'service_id' => $service->id,
                'starts_at' => $startsAt,
                'ends_at' => $startsAt->addMinutes($service->duration),
                'status' => AppointmentStatus::Confirmed,
            ]));
        });

        // E-posta kuyruğa atılır; müşteri gönderimin bitmesini beklemez.
        $appointment->notify(new AppointmentBooked($appointment));

        return $appointment;
    }
}
