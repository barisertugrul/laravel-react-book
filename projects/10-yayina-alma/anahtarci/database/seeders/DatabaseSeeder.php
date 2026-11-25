<?php

namespace Database\Seeders;

use App\Actions\ActivateLicense;
use App\Actions\IssueLicense;
use App\Actions\MarkOrderPaid;
use App\Enums\LicenseEventType;
use App\Enums\LicenseStatus;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Exceptions\LicenseApiException;
use App\Models\Activation;
use App\Models\License;
use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com', 'role' => Role::Admin]);
        $elif = User::factory()->create(['name' => 'Elif Şahin', 'email' => 'musteri@example.com']);
        $mert = User::factory()->create(['name' => 'Mert Kaya', 'email' => 'mert@example.com']);

        $catalog = [
            ['Ajans Asistanı', 'ajans-asistani', 'Müşteri sitelerinizi tek panelden izleyin: sağlık kontrolleri, güncellemeler, yedek durumu.',
                'Ajans panelinize (Bölüm 8) bağlanan yardımcı eklenti. Sitenin WordPress ve eklenti sürümlerini, PHP sürümünü ve son yedeğini güvenli bir kanaldan bildirir.',
                [['Tek site', '1.490,00', 1], ['5 site', '3.990,00', 5, 'En çok tercih edilen'], ['Sınırsız', '7.990,00', null]]],
            ['Form Kalesi', 'form-kalesi', 'İletişim formlarınızı spam ve bot saldırılarından koruyun.', null,
                [['Tek site', '690,00', 1], ['5 site', '1.790,00', 5, 'En çok tercih edilen'], ['Sınırsız', '3.490,00', null]]],
            ['Hızlı Yedek', 'hizli-yedek', 'Zamanlanmış yedekler ve tek tıkla geri yükleme.', null,
                [['Tek site', '990,00', 1], ['5 site', '2.490,00', 5, 'En çok tercih edilen'], ['Sınırsız', '4.990,00', null]]],
        ];

        foreach ($catalog as [$name, $slug, $summary, $description, $plans]) {
            $product = Product::create(compact('name', 'slug', 'summary', 'description'));

            foreach ($plans as $i => $plan) {
                [$planName, $price, $sites] = $plan;
                $badge = $plan[3] ?? null; // vitrinde vurgu etiketi (Bölüm 10); çoğu planda yok
                $product->plans()->create(['name' => $planName, 'price' => Money::parse($price), 'max_sites' => $sites, 'badge' => $badge, 'sort' => $i]);
            }
        }

        $activate = new ActivateLicense;
        // Örnek veride saatler gerçekçi olsun: "bugünden N gün önce, saat SS:DD".
        // "Bugün" bir kez alınır: at() içinde now() zaten geriye alınmış olur, ona göre hesaplasaydık kayardı.
        $today = now();
        $day = fn (int $daysAgo, string $time) => $today->subDays($daysAgo)->setTimeFromTimeString($time);

        // Elif: 14 ay önce Form Kalesi aldı (güncelleme süresi bitti, eklenti çalışmaya devam ediyor)
        $this->at($day(425, '10:12'), function () use ($elif, $activate, $day) {
            $license = $this->buy($elif, 'form-kalesi', 'Tek site');
            $this->at($day(425, '10:31'), fn () => $activate->handle($license->key, 'elifsahin-ajans.com', '203.0.113.10'));
        });

        // Elif: 2 ay önce Ajans Asistanı 5 site aldı
        $this->at($day(61, '14:05'), function () use ($elif, $activate, $day) {
            $license = $this->buy($elif, 'ajans-asistani', '5 site');

            $this->at($day(61, '14:22'), fn () => $activate->handle($license->key, 'elifsahin-ajans.com', '203.0.113.10'));
            $this->at($day(52, '09:47'), fn () => $activate->handle($license->key, 'https://www.kuafor-meltem.com/', '203.0.113.11'));
            $this->at($day(47, '16:03'), fn () => $activate->handle($license->key, 'eski-musteri.com', '203.0.113.20'));
            $this->at($day(40, '11:38'), fn () => $activate->handle($license->key, 'dis-klinigi-izmir.com.tr', '203.0.113.12'));

            // Müşterisiyle çalışmayı bırakınca o siteyi portaldan kaldırdı (LicenseController::destroyActivation ile aynı iş)
            $this->at($day(18, '17:20'), function () use ($license) {
                $activation = $license->activations()->where('domain', 'eski-musteri.com')->sole();
                $license->log(LicenseEventType::Removed, $activation->domain, '203.0.113.10');
                $activation->delete();
            });

            // Kuaför sitesi yeni sunucuya taşındı, eklenti yeniden kuruldu: yeni yer harcanmadı
            $this->at($day(9, '08:55'), fn () => $activate->handle($license->key, 'kuafor-meltem.com', '203.0.113.31'));
        });

        // Eklentiler günde bir kez "check" yapar: son kontroller yakın zamanda
        foreach (['elifsahin-ajans.com' => 3, 'kuafor-meltem.com' => 7, 'dis-klinigi-izmir.com.tr' => 11] as $domain => $hoursAgo) {
            Activation::where('domain', $domain)->update(['last_seen_at' => now()->subHours($hoursAgo)->subMinutes(17)]);
        }

        // Mert: aldı, etkinleştirdi, iade istedi → lisans iptal edildi; sonra yeniden etkinleştirmeyi denedi
        $this->at($day(20, '13:40'), function () use ($mert, $activate, $day) {
            $license = $this->buy($mert, 'ajans-asistani', 'Tek site');
            $this->at($day(20, '13:52'), fn () => $activate->handle($license->key, 'mertkaya.dev', '198.51.100.7'));

            $this->at($day(17, '10:15'), function () use ($license) {
                $license->update(['status' => LicenseStatus::Revoked, 'revoked_at' => now(), 'revoke_reason' => "İade: {$license->order->number}"]);
                $license->log(LicenseEventType::Revoked, meta: ['reason' => "İade: {$license->order->number}", 'by' => 'test@example.com']);
            });

            // Sitesi bir sonraki kontrolde "geçersiz" yanıtını aldı, ardından başka bir sitede denedi
            $this->at($day(16, '06:02'), fn () => $license->activations()->update(['last_seen_at' => now()]));
            $this->at($day(15, '21:47'), function () use ($license, $activate) {
                try {
                    $activate->handle($license->key, 'yeni-site.mertkaya.dev', '198.51.100.7');
                } catch (LicenseApiException) {
                    // Beklenen: license_revoked. ActivateLicense reddi olay kaydına kendisi yazar.
                }
            });
        });
    }

    /** Gerçek akıştan geçen satın alma: sipariş → ödeme → lisans */
    private function buy(User $user, string $product, string $plan): License
    {
        $plan = Plan::whereRelation('product', 'slug', $product)->where('name', $plan)->firstOrFail();
        $order = $user->orders()->create(['plan_id' => $plan->id, 'status' => OrderStatus::Pending, 'total' => $plan->price, 'payment_provider' => 'fake']);

        (new MarkOrderPaid)->handle($order, $order->total, 'FAKE-SEED'.$order->id);

        // Kuyruktaki iş de aynı lisansı üretmeye çalışacak; IssueLicense idempotent olduğu için mevcut lisansı bulup çıkar
        return (new IssueLicense)->handle($order);
    }

    /** Geçmiş tarihli örnek veri: "şimdi"yi geçici olarak geri alır */
    private function at(CarbonImmutable $moment, callable $callback): void
    {
        $previous = Carbon::getTestNow();
        Carbon::setTestNow($moment);
        CarbonImmutable::setTestNow($moment);

        try {
            $callback();
        } finally {
            Carbon::setTestNow($previous);
            CarbonImmutable::setTestNow($previous);
        }
    }
}
