<?php

namespace App\Console\Commands;

use App\Support\LicenseKey;
use App\Support\Signer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Bir WordPress sitesinin yapacağını komut satırından yapan deneme istemcisi.
 * Asıl istemci Bölüm 8'deki WordPress eklentisidir; o da aynı üç adımı izler:
 *
 *   php artisan license:client activate --key=ANH1.... --domain=ornek-ajans.com
 *   php artisan license:client check
 *   php artisan license:client deactivate
 *
 * Token, gerçek bir eklentinin wp_options tablosunda tutacağı gibi bir dosyada saklanır.
 */
#[Signature('license:client {action : activate, check veya deactivate} {--key= : Lisans anahtarı (activate)} {--domain=ornek-ajans.com : Sitenin adresi (activate)} {--url= : Lisans sunucusu (varsayılan: APP_URL)}')]
#[Description('Lisans API\'sini bir WordPress sitesi gibi çağırır')]
class LicenseClient extends Command
{
    private const STATE_FILE = 'license-client.json';

    public function handle(): int
    {
        $state = json_decode(Storage::get(self::STATE_FILE) ?? '{}', true) ?: [];
        $base = rtrim($this->option('url') ?: ($state['url'] ?? config('app.url')), '/').'/api/v1/licenses';

        try {
            return match ($this->argument('action')) {
                'activate' => $this->activate($base),
                'check' => $this->authorized($state, fn () => Http::acceptJson()->withToken($state['token'])->get("{$base}/check")),
                'deactivate' => $this->deactivate($state, $base),
                default => $this->failWith('Bilinmeyen işlem. activate, check veya deactivate yazın.'),
            };
        } catch (ConnectionException) {
            return $this->failWith("Sunucuya bağlanılamadı ({$base}). composer run dev çalışıyor mu?");
        }
    }

    private function activate(string $base): int
    {
        $key = (string) $this->option('key');

        // Eklenti, anahtarı sunucuya göndermeden önce imzasını açık anahtarla kendisi doğrular
        $payload = LicenseKey::parse($key);

        if ($payload === null) {
            return $this->failWith('Anahtarın biçimi veya imzası geçersiz; sunucuya hiç sorulmadı.');
        }

        $this->components->info("Anahtar imzası geçerli: lisans #{$payload['lid']}, ürün {$payload['prd']}");

        $response = Http::acceptJson()->post("{$base}/activate", ['key' => $key, 'domain' => $this->option('domain')]);
        $this->show($response);

        if ($response->successful()) {
            Storage::put(self::STATE_FILE, json_encode([
                'url' => preg_replace('#/api/v1/licenses$#', '', $base),
                'domain' => $response->json('domain'),
                'token' => $response->json('token'),
            ], JSON_PRETTY_PRINT));
            $this->components->info('Token kaydedildi: storage/app/private/'.self::STATE_FILE);
        }

        return $response->successful() ? self::SUCCESS : self::FAILURE;
    }

    private function deactivate(array $state, string $base): int
    {
        $result = $this->authorized($state, fn () => Http::acceptJson()->withToken($state['token'])->post("{$base}/deactivate"));

        if ($result === self::SUCCESS) {
            Storage::delete(self::STATE_FILE);
        }

        return $result;
    }

    /** Token gerektiren çağrılar */
    private function authorized(array $state, callable $request): int
    {
        if (empty($state['token'])) {
            return $this->failWith('Önce etkinleştirin: php artisan license:client activate --key=...');
        }

        $response = $request();
        $this->show($response);

        return $response->successful() ? self::SUCCESS : self::FAILURE;
    }

    private function show(Response $response): void
    {
        // Yanıt gerçekten lisans sunucumuzdan mı geldi? Eklenti, imzasız veya bozuk imzalı yanıta güvenmez.
        $signature = base64_decode((string) $response->header('X-License-Signature'), true);
        $trusted = $signature !== false && Signer::verify($signature, $response->body(), Signer::publicKey());

        $this->line(sprintf('HTTP %d · imza: %s', $response->status(), $trusted ? '<fg=green>doğrulandı</>' : '<fg=red>GEÇERSİZ</>'));
        $this->line(json_encode($response->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function failWith(string $message): int
    {
        $this->components->error($message);

        return self::FAILURE;
    }
}
