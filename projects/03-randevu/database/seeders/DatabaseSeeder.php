<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Models\Service;
use App\Models\User;
use App\Models\WorkingHour;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Pazartesi-Cumartesi 09:00-19:00, Pazar kapalı
        foreach (range(1, 7) as $weekday) {
            WorkingHour::create([
                'weekday' => $weekday,
                'is_open' => $weekday !== 7,
                'opens_at' => '09:00',
                'closes_at' => $weekday === 6 ? '17:00' : '19:00',
            ]);
        }

        $services = collect([
            ['Saç Kesimi', 30, 350],
            ['Sakal Tıraşı', 15, 150],
            ['Saç + Sakal', 45, 450],
            ['Fön', 30, 250],
            ['Saç Boyama', 90, 1200],
        ])->map(fn ($s) => Service::create(['name' => $s[0], 'duration' => $s[1], 'price' => $s[2]]));

        // Bu hafta ve gelecek hafta, her açık güne birkaç örnek randevu
        $customers = ['Ahmet Yılmaz', 'Zeynep Kaya', 'Mehmet Demir', 'Elif Şahin', 'Can Öztürk', 'Selin Arslan', 'Burak Çelik', 'Deniz Aydın'];
        $plan = [['09:30', 0], ['10:15', 2], ['11:30', 4], ['14:00', 1], ['15:00', 3], ['16:30', 0]];
        $monday = CarbonImmutable::now()->startOfWeek();

        foreach (range(0, 12) as $dayOffset) {
            $day = $monday->addDays($dayOffset);

            if ($day->isSunday()) {
                continue;
            }

            foreach ($plan as $i => [$time, $serviceIndex]) {
                // Her gün bazı saatler boş kalsın
                if (($dayOffset + $i) % 3 === 0) {
                    continue;
                }

                $service = $services[$serviceIndex];
                $start = $day->setTimeFromTimeString($time);
                $status = match (true) {
                    $start->isPast() => AppointmentStatus::Completed,
                    ($dayOffset + $i) % 7 === 0 => AppointmentStatus::Cancelled,
                    default => AppointmentStatus::Confirmed,
                };

                $service->appointments()->create([
                    'customer_name' => $customers[($dayOffset * 3 + $i) % count($customers)],
                    'customer_email' => 'musteri'.($dayOffset * 10 + $i).'@ornek.com',
                    'customer_phone' => '+90 5'.str_pad((string) (30 + $i), 2, '0').' 000 00 0'.$dayOffset,
                    'starts_at' => $start,
                    'ends_at' => $start->addMinutes($service->duration),
                    'status' => $status,
                ]);
            }
        }
    }
}
