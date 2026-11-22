<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Models\Customer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com', 'role' => Role::Admin]);
        $emre = User::factory()->create(['name' => 'Emre Yıldız', 'email' => 'emre@example.com', 'role' => Role::Technician]);
        $zehra = User::factory()->create(['name' => 'Zehra Koç', 'email' => 'zehra@example.com', 'role' => Role::Technician]);

        $devices = [
            ['Telefon', 'Apple', 'iPhone 13', 'Ekran kırık, dokunmatik çalışmıyor', 'Kılıf', 4200],
            ['Telefon', 'Samsung', 'Galaxy S22', 'Şarj olmuyor', null, 900],
            ['Dizüstü', 'Lenovo', 'IdeaPad 5', 'Açılmıyor, şarj ışığı yanıyor', 'Adaptör', 1800],
            ['Dizüstü', 'Asus', 'Vivobook 15', 'Klavyeye su döküldü', 'Adaptör, çanta', 2500],
            ['Tablet', 'Apple', 'iPad 9', 'Batarya çabuk bitiyor', null, 1600],
            ['Telefon', 'Xiaomi', 'Redmi Note 12', 'Hoparlörden ses gelmiyor', null, 650],
            ['Masaüstü', 'Casper', 'Nirvana', 'Mavi ekran hatası', 'Güç kablosu', null],
            ['Telefon', 'Huawei', 'P30 Lite', 'Kamera bulanık', 'Kılıf', 750],
            ['Dizüstü', 'HP', 'Pavilion 15', 'Fan sesi çok yüksek', 'Adaptör', 800],
            ['Oyun konsolu', 'Sony', 'PlayStation 5', 'HDMI çıkışı görüntü vermiyor', 'Kumanda', 3200],
            ['Telefon', 'Apple', 'iPhone 11', 'Face ID çalışmıyor', null, 2800],
            ['Akıllı saat', 'Apple', 'Watch SE', 'Ekran camı çatlak', 'Kordon', 1500],
        ];
        $names = ['Ahmet Yılmaz', 'Zeynep Kaya', 'Mehmet Demir', 'Elif Şahin', 'Can Öztürk', 'Selin Arslan', 'Burak Çelik', 'Deniz Aydın', 'Hakan Polat', 'Gizem Kurt'];

        // Her kaydı farklı bir aşamaya kadar ilerlet: durum geçmişi de buna göre oluşsun
        $paths = [
            [],
            ['diagnosing'],
            ['diagnosing', 'waiting_approval'],
            ['diagnosing', 'repairing'],
            ['diagnosing', 'repairing', 'ready'],
            ['diagnosing', 'repairing', 'ready', 'delivered'],
            ['diagnosing', 'cancelled'],
        ];
        $notes = [
            'diagnosing' => 'Cihaz incelemeye alındı.',
            'waiting_approval' => 'Anakart arızası tespit edildi, onayınızı bekliyoruz.',
            'repairing' => 'Parça siparişi verildi, tamire başlandı.',
            'ready' => 'Tamir tamamlandı, testler yapıldı.',
            'delivered' => 'Cihaz müşteriye teslim edildi.',
            'cancelled' => 'Müşteri tamiri onaylamadı.',
        ];

        foreach ($devices as $i => [$type, $brand, $model, $problem, $accessories, $cost]) {
            $customer = Customer::create([
                'name' => $names[$i % count($names)],
                'phone' => '53'.str_pad((string) (21234500 + $i * 137), 8, '0', STR_PAD_LEFT),
                'email' => 'musteri'.$i.'@ornek.com',
            ]);
            $technician = [$emre, $zehra, null][$i % 3];
            $path = $paths[$i % count($paths)];
            $at = CarbonImmutable::now()->subDays(12 - $i)->setTime(10, 0);

            $ticket = $customer->tickets()->create([
                'device_type' => $type, 'brand' => $brand, 'model' => $model,
                'serial_number' => strtoupper(substr(md5($model.$i), 0, 10)),
                'problem' => $problem, 'accessories' => $accessories, 'estimated_cost' => $cost,
                'assigned_to' => $path ? ($technician ?? $emre)->id : $technician?->id,
                'status' => $path ? end($path) : 'received',
                'final_cost' => in_array('ready', $path, true) ? $cost : null,
            ]);
            $ticket->forceFill(['created_at' => $at])->saveQuietly();

            $ticket->events()->create(['user_id' => $admin->id, 'to_status' => 'received', 'note' => 'Cihaz teslim alındı.', 'created_at' => $at]);
            $from = 'received';
            foreach ($path as $step => $to) {
                $ticket->events()->create([
                    'user_id' => ($technician ?? $emre)->id,
                    'from_status' => $from, 'to_status' => $to, 'note' => $notes[$to],
                    'created_at' => $at->addHours(($step + 1) * 20),
                ]);
                $from = $to;
            }
        }
    }
}
