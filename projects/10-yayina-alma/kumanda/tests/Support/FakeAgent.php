<?php

namespace Tests\Support;

use App\Agent\AgentProtocol;
use App\Support\Signer;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Testler için Ajans Asistanı eklentisinin PHP'deki ikizi: gerçek eklentiyle aynı kuralları
 * uygular (imza, zaman damgası, nonce, jeton) ve yanıtlarını imzalar. Gerçek WordPress gerekmez.
 */
class FakeAgent
{
    public string $secretKey;

    public string $publicKey;

    public string $token;

    public ?string $panelPublicKey = null;

    public ?string $siteId = null;

    /** @var array<string, true> */
    public array $seenNonces = [];

    /** @var array<string, mixed> */
    public array $report;

    /** Ana sayfanın durum kodu; null = bağlantı kurulamıyor */
    public ?int $homepageStatus = 200;

    /** Eklenti silinmiş gibi davran: REST rotası yok, imzasız 404 */
    public bool $uninstalled = false;

    /** Doluysa REST istekleri bu adrese yönlendirilir (302) */
    public ?string $redirectTo = null;

    /** @var list<Request> */
    public array $requests = [];

    /** Doluysa eklentinin yerine bu yanıt verir (ör. araya giren birinin tekrar oynattığı yanıt) */
    public ?\Closure $intercept = null;

    /**
     * Gelen komutlar (Bölüm 9): [rota, gövde]
     *
     * @var list<array{0: string, 1: array<string, mixed>}>
     */
    public array $commands = [];

    /**
     * Doluysa komutu bu işler: fn (string $route, array $data) => [durum, gövde].
     * Boşsa kurma/güncelleme başarılı sayılır.
     */
    public ?\Closure $onCommand = null;

    public function __construct(public string $url = 'https://lale-kafe.test')
    {
        [$this->secretKey, $this->publicKey] = Signer::generateKeyPair();
        $this->token = bin2hex(random_bytes(24));
        $this->report = self::healthyReport();
    }

    public function connectionKey(): string
    {
        $json = json_encode(['u' => $this->url, 'a' => "{$this->url}/wp-json/", 't' => $this->token, 'k' => base64_encode($this->publicKey)], JSON_UNESCAPED_SLASHES);

        return AgentProtocol::VERSION.'.'.Signer::base64UrlEncode($json);
    }

    public function fake(): static
    {
        Http::fake([
            "{$this->url}/wp-json/*" => fn (Request $request) => $this->handle($request),
            "{$this->url}*" => fn () => $this->homepageStatus === null
                ? Http::failedConnection()
                : Http::response('<html>Lale Kafe</html>', $this->homepageStatus),
        ]);

        return $this;
    }

    public function handle(Request $request): PromiseInterface
    {
        $this->requests[] = $request;

        if ($this->intercept !== null) {
            return ($this->intercept)($request);
        }

        if ($this->redirectTo !== null) {
            return Http::response('', 302, ['Location' => $this->redirectTo]);
        }

        if ($this->uninstalled) {
            return Http::response(['code' => 'rest_no_route'], 404);
        }

        $route = '/'.ltrim(substr(parse_url($request->url(), PHP_URL_PATH), strlen('/wp-json')), '/');
        $nonce = $request->header(AgentProtocol::HEADER_NONCE)[0] ?? '';

        if ($route === '/'.AgentProtocol::ROUTE_PAIR) {
            $data = json_decode($request->body(), true);
            $key = base64_decode($data['panel_public_key'] ?? '');
            if ($error = $this->verify($request, $route, $key, (string) ($data['site_id'] ?? ''))) {
                return $this->respond($nonce, 401, ['error' => $error, 'message' => "Reddedildi: {$error}"]);
            }
            if (! hash_equals($this->token, (string) ($data['token'] ?? ''))) {
                return $this->respond($nonce, 403, ['error' => 'invalid_token', 'message' => 'Bağlantı anahtarı geçersiz, kullanılmış ya da süresi dolmuş.']);
            }
            $this->token = bin2hex(random_bytes(24)); // tek kullanımlık
            $this->panelPublicKey = $key;
            $this->siteId = (string) $data['site_id'];

            return $this->respond($nonce, 200, ['paired' => true]);
        }

        if ($this->panelPublicKey === null) {
            return $this->respond($nonce, 409, ['error' => 'not_paired', 'message' => 'Bu site bir panele bağlı değil.']);
        }

        if ($error = $this->verify($request, $route, $this->panelPublicKey, (string) $this->siteId)) {
            return $this->respond($nonce, 401, ['error' => $error, 'message' => "Reddedildi: {$error}"]);
        }

        // Sağlık raporu dışındaki her imzalı istek bir komuttur (eklenti kur/güncelle, lisans)
        if ($route !== '/'.AgentProtocol::ROUTE_HEALTH) {
            $data = (array) json_decode($request->body(), true);
            $this->commands[] = [ltrim($route, '/'), $data];
            [$status, $body] = $this->onCommand !== null
                ? ($this->onCommand)(ltrim($route, '/'), $data)
                : [200, self::installedPlugin($data)];

            return $this->respond($nonce, $status, $body);
        }

        return $this->respond($nonce, 200, $this->report);
    }

    /**
     * Eklentinin başarılı kurma/güncelleme yanıtı
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function installedPlugin(array $data): array
    {
        $file = $data['file'] ?? (($data['slug'] ?? 'paket').'/'.($data['slug'] ?? 'paket').'.php');

        return ['file' => $file, 'name' => ucwords(str_replace('-', ' ', strtok($file, '/'))), 'version' => '2.0.0', 'active' => true];
    }

    /** Eklentideki Ajans_Asistani_Rest::verify() ile aynı adımlar; hata kodu ya da null */
    private function verify(Request $request, string $route, string $publicKey, string $expectedSite): ?string
    {
        $timestamp = $request->header(AgentProtocol::HEADER_TIMESTAMP)[0] ?? '';
        $nonce = $request->header(AgentProtocol::HEADER_NONCE)[0] ?? '';
        $site = $request->header(AgentProtocol::HEADER_SITE)[0] ?? '';
        $signature = Signer::base64UrlDecode($request->header(AgentProtocol::HEADER_SIGNATURE)[0] ?? '');

        if (! ctype_digit($timestamp) || ! preg_match('/^[a-f0-9]{32}$/', $nonce) || ! $signature) {
            return 'unsigned';
        }
        if (abs(time() - (int) $timestamp) > AgentProtocol::MAX_CLOCK_SKEW) {
            return 'stale';
        }
        if (! hash_equals($expectedSite, $site)) {
            return 'wrong_site';
        }
        $message = AgentProtocol::requestMessage($request->method(), $route, $timestamp, $nonce, $request->body());
        if (! Signer::verify($signature, $message, $publicKey)) {
            return 'bad_signature';
        }
        if (isset($this->seenNonces[$nonce])) {
            return 'replay';
        }
        $this->seenNonces[$nonce] = true;

        return null;
    }

    /** @param array<string, mixed> $data */
    public function respond(string $requestNonce, int $status, array $data): PromiseInterface
    {
        $body = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = (string) time();
        $signature = Signer::sign(AgentProtocol::responseMessage($requestNonce, $status, $timestamp, $body), $this->secretKey);

        return Http::response($body, $status, [
            'Content-Type' => 'application/json',
            AgentProtocol::HEADER_TIMESTAMP => $timestamp,
            AgentProtocol::HEADER_SIGNATURE => Signer::base64UrlEncode($signature),
        ]);
    }

    /** @return array<string, mixed> */
    public static function healthyReport(): array
    {
        return [
            'site_name' => 'Lale Kafe',
            'wp_version' => '7.1.5',
            'php_version' => '8.3.14',
            'agent_version' => '1.0.0',
            'core_update' => null,
            'plugins' => [
                ['file' => 'ajans-asistani/ajans-asistani.php', 'name' => 'Ajans Asistanı', 'version' => '1.0.0', 'active' => true, 'update' => null],
                ['file' => 'contact-form-7/wp-contact-form-7.php', 'name' => 'Contact Form 7', 'version' => '6.1', 'active' => true, 'update' => null],
            ],
            'themes' => [
                ['slug' => 'twentytwentyfive', 'name' => 'Twenty Twenty-Five', 'version' => '1.3', 'active' => true, 'update' => null],
            ],
            'debug' => false,
            'debug_display' => false,
            'file_edit_disabled' => true,
            'admin_count' => 1,
        ];
    }
}
