<?php

namespace App\Support;

use App\Models\Appointment;
use App\Models\Service;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable;

/**
 * Bir hizmet için, verilen günde alınabilecek randevu saatlerini hesaplar.
 * Bu sınıf yalnızca hesap yapar; hiçbir şeyi değiştirmez. Bu yüzden test etmesi kolaydır.
 */
class Availability
{
    /** Saatler kaç dakikada bir önerilsin (09:00, 09:15, 09:30...) */
    public const STEP_MINUTES = 15;

    /** Şu andan en az kaç dakika sonrası için randevu alınabilsin */
    public const MIN_NOTICE_MINUTES = 60;

    /** Kaç gün ileriye randevu verilsin */
    public const BOOKING_WINDOW_DAYS = 14;

    /**
     * @return list<string> ["09:00", "09:15", ...]
     */
    public function slots(Service $service, CarbonImmutable $date): array
    {
        $date = $date->startOfDay();

        if (! $this->isBookableDate($date)) {
            return [];
        }

        $hours = WorkingHour::where('weekday', $date->dayOfWeekIso)->first();

        if (! $hours?->is_open) {
            return [];
        }

        $open = $date->setTimeFromTimeString($hours->opens_at);
        $close = $date->setTimeFromTimeString($hours->closes_at);
        $earliest = CarbonImmutable::now()->addMinutes(self::MIN_NOTICE_MINUTES);

        // O günün dolu aralıklarını tek sorguda çekiyoruz; döngüde sorgu yok.
        $busy = Appointment::active()->overlapping($open, $close)->get(['starts_at', 'ends_at']);

        $slots = [];

        for ($start = $open; $start->addMinutes($service->duration) <= $close; $start = $start->addMinutes(self::STEP_MINUTES)) {
            $end = $start->addMinutes($service->duration);

            if ($start < $earliest) {
                continue;
            }

            $taken = $busy->contains(
                fn (Appointment $appointment) => $appointment->starts_at < $end && $start < $appointment->ends_at,
            );

            if (! $taken) {
                $slots[] = $start->format('H:i');
            }
        }

        return $slots;
    }

    public function isBookableDate(CarbonImmutable $date): bool
    {
        $today = CarbonImmutable::today();

        return $date->betweenIncluded($today, $today->addDays(self::BOOKING_WINDOW_DAYS));
    }

    /**
     * Önümüzdeki günlerin listesi ve her günün açık olup olmadığı.
     *
     * @return list<array{date: string, open: bool}>
     */
    public function days(): array
    {
        $openDays = WorkingHour::where('is_open', true)->pluck('weekday')->all();
        $today = CarbonImmutable::today();

        return collect(range(0, self::BOOKING_WINDOW_DAYS))
            ->map(fn (int $i) => $today->addDays($i))
            ->map(fn (CarbonImmutable $day) => [
                'date' => $day->toDateString(),
                'open' => in_array($day->dayOfWeekIso, $openDays, true),
            ])
            ->all();
    }
}
