<?php

namespace Database\Seeders;

use App\Enums\AgencyRole;
use App\Health\HealthEvaluator;
use App\Models\Agency;
use App\Models\Site;
use App\Models\User;
use App\Support\Signer;
use App\Tenancy\CurrentAgency;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Örnek veri. Siteler ".test" adresli kurgusal sitelerdir; kontrol geçmişleri burada üretilir.
 * Gerçek siteler (WordPress Playground) panele "Site ekle" ile bağlanır.
 *
 * Not: zamanlayıcı 15 dakikada bir tüm siteleri kontrol eder; kurgusal siteler gerçekten
 * açılamayacağı için bir süre sonra "Kritik" görünürler. Baştan almak için: migrate:fresh --seed
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        mt_srand(8); // her çalıştırmada aynı "rastgele" yanıt süreleri: ekran görüntüleri tutarlı kalsın

        $test = User::factory()->create(['name' => 'Test User', 'email' => 'test@example.com']);
        $ayse = User::factory()->create(['name' => 'Ayşe Demir', 'email' => 'ayse@example.com']);
        $zeynep = User::factory()->create(['name' => 'Zeynep Arslan', 'email' => 'zeynep@example.com']);

        $mavi = Agency::create(['name' => 'Mavi Pixel Dijital']);
        $mavi->addMember($test, AgencyRole::Owner);
        $mavi->addMember($ayse, AgencyRole::Member);
        $test->switchAgency($mavi);
        $ayse->switchAgency($mavi);

        // test@example.com iki ajansta: ajans değiştiriciyi denemek için
        $kuzey = Agency::create(['name' => 'Kuzey Web Atölyesi']);
        $kuzey->addMember($zeynep, AgencyRole::Owner);
        $kuzey->addMember($test, AgencyRole::Member);
        $zeynep->switchAgency($kuzey);

        $mavi->invitations()->create([
            'email' => 'deniz@example.com', 'role' => AgencyRole::Member, 'invited_by' => $test->id, 'expires_at' => now()->addDays(6),
        ]);

        app(CurrentAgency::class)->run($mavi, function (): void {
            $this->site('Lale Kafe', 'https://lalekafe.test', 'ok', 420);
            $this->site('Ege Diş Kliniği', 'https://egedis.test', 'updates', 610);
            $this->site('Atlas Hukuk Bürosu', 'https://atlashukuk.test', 'down', 380);
            $this->site('Pati Veteriner', 'https://pativeteriner.test', 'ssl', 520);
            $this->site('Kitap Kurdu', 'https://kitapkurdu.test', 'ok', 290);
            $this->site('Rota Turizm', 'https://rotaturizm.test', 'debug', 1450);
        });

        app(CurrentAgency::class)->run($kuzey, function (): void {
            $this->site('Kuzey Mobilya', 'https://kuzeymobilya.test', 'ok', 350);
            $this->site('Deniz Yapı', 'https://denizyapi.test', 'updates', 700);
        });
    }

    /** Son 12 saatin 15 dakikalık kontrollerini üretir; en son kontrol $scenario'ya göre bozulur */
    private function site(string $name, string $url, string $scenario, int $baseMs): void
    {
        [, $agentPublic] = Signer::generateKeyPair();
        [$panelSecret] = Signer::generateKeyPair();

        $site = Site::create([
            'name' => $name,
            'url' => $url,
            'api_url' => "{$url}/wp-json/",
            'agent_public_key' => base64_encode($agentPublic),
            'panel_secret_key' => base64_encode($panelSecret),
        ]);
        $site->forceFill(['created_at' => now()->subDays(40)])->save();

        $evaluator = new HealthEvaluator;
        $now = CarbonImmutable::now()->startOfMinute();
        $sslExpires = $scenario === 'ssl' ? $now->addDays(9) : $now->addDays(mt_rand(40, 85));
        $last = null;

        for ($i = 47; $i >= 0; $i--) {
            $at = $now->subMinutes($i * 15);
            $latest = $i < 4; // son bir saat: senaryo devrede
            $down = $scenario === 'down' && $latest;
            $report = $down ? null : $this->report($name, $latest ? $scenario : 'ok');
            $ms = $down ? null : (int) round($baseMs * (0.8 + mt_rand(0, 40) / 100));

            $result = $evaluator->evaluate($url, $down ? null : 200, $ms, $down ? 'cURL error 28: Operation timed out after 10001 milliseconds' : null, $sslExpires, $report, null);

            $last = $site->checks()->create([
                'status' => $result->status,
                'http_status' => $down ? null : 200,
                'response_ms' => $ms,
                'ssl_expires_at' => $sslExpires,
                'issues' => $result->issues,
                'report' => $report,
                'error' => $down ? 'Site açılmıyor: zaman aşımı (10 sn)' : null,
                'checked_at' => $at,
            ]);
        }

        $lastReport = $this->report($name, $scenario === 'down' ? 'ok' : $scenario);
        $site->forceFill([
            'health_status' => $last->status,
            'status_changed_at' => in_array($scenario, ['ok', 'ssl'], true) ? $now->subDays(3) : $now->subMinutes(45),
            'last_checked_at' => $last->checked_at,
            'response_ms' => $last->response_ms,
            'ssl_expires_at' => $sslExpires,
            'wp_version' => $lastReport['wp_version'],
            'php_version' => $lastReport['php_version'],
            'agent_version' => '1.0.0',
            'pending_updates' => collect([...$lastReport['plugins'], ...$lastReport['themes']])->whereNotNull('update')->count() + ($lastReport['core_update'] ? 1 : 0),
        ])->save();
    }

    /** @return array<string, mixed> */
    private function report(string $name, string $scenario): array
    {
        $updates = $scenario === 'updates';

        return [
            'site_name' => $name,
            'wp_version' => $updates ? '7.0.3' : '7.1.5',
            'php_version' => $scenario === 'debug' ? '7.4.33' : '8.3.14',
            'agent_version' => '1.0.0',
            'core_update' => $updates ? '7.1.5' : null,
            'plugins' => [
                ['file' => 'ajans-asistani/ajans-asistani.php', 'name' => 'Ajans Asistanı', 'version' => '1.0.0', 'active' => true, 'update' => null],
                ['file' => 'woocommerce/woocommerce.php', 'name' => 'WooCommerce', 'version' => $updates ? '10.2.1' : '10.3.0', 'active' => true, 'update' => $updates ? '10.3.0' : null],
                ['file' => 'wordpress-seo/wp-seo.php', 'name' => 'Yoast SEO', 'version' => '26.4', 'active' => true, 'update' => $updates ? '26.5' : null],
                ['file' => 'contact-form-7/wp-contact-form-7.php', 'name' => 'Contact Form 7', 'version' => '6.1.3', 'active' => true, 'update' => null],
                ['file' => 'form-kalesi/form-kalesi.php', 'name' => 'Form Kalesi', 'version' => '2.4.0', 'active' => true, 'update' => null],
                ['file' => 'hello-dolly/hello.php', 'name' => 'Hello Dolly', 'version' => '1.7.2', 'active' => false, 'update' => null],
            ],
            'themes' => [
                ['slug' => 'twentytwentyfive', 'name' => 'Twenty Twenty-Five', 'version' => '1.3', 'active' => true, 'update' => null],
            ],
            'debug' => $scenario === 'debug',
            'debug_display' => $scenario === 'debug',
            'file_edit_disabled' => $scenario !== 'debug',
            'admin_count' => $scenario === 'debug' ? 5 : 2,
        ];
    }
}
