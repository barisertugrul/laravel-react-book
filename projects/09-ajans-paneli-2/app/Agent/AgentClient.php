<?php

namespace App\Agent;

use App\Models\Site;
use App\Support\Signer;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Http\Client\Response;

/**
 * Panelden ajana imzalı istek gönderir, ajanın imzalı yanıtını doğrular.
 *
 *   panel ──(panelin gizli anahtarıyla imzalı istek)──▶ eklenti: panelin AÇIK anahtarıyla doğrular
 *   panel ◀──(eklentinin gizli anahtarıyla imzalı yanıt)── eklenti
 *   panel: eklentinin AÇIK anahtarıyla doğrular
 */
class AgentClient
{
    public function __construct(private Http $http) {}

    /**
     * @param  array<string, mixed>|null  $payload
     * @param  int  $timeout  saniye; eklenti kurma/güncelleme gibi komutlar sağlık raporundan çok daha uzun sürer
     * @return array<string, mixed>
     *
     * @throws AgentException
     */
    public function send(Site $site, string $method, string $route, ?array $payload = null, int $timeout = 15): array
    {
        $body = $payload === null ? '' : json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $timestamp = (string) now()->getTimestamp();
        $nonce = bin2hex(random_bytes(16)); // her istekte yeni: eklenti aynı nonce'u ikinci kez kabul etmez

        $signature = Signer::sign(
            AgentProtocol::requestMessage($method, $route, $timestamp, $nonce, $body),
            base64_decode($site->panel_secret_key),
        );

        try {
            $response = $this->http
                ->timeout($timeout)
                // Yönlendirme TAKİP EDİLMEZ: imza bu adrese ve yönteme bağlı. Takip edilseydi istek
                // başka bir sunucuya gidebilir, POST da GET'e dönüşebilirdi (301/302'de istemciler böyle yapar).
                ->withoutRedirecting()
                ->acceptJson()
                ->withHeaders([
                    AgentProtocol::HEADER_SITE => (string) $site->id,
                    AgentProtocol::HEADER_TIMESTAMP => $timestamp,
                    AgentProtocol::HEADER_NONCE => $nonce,
                    AgentProtocol::HEADER_SIGNATURE => Signer::base64UrlEncode($signature),
                ])
                ->withBody($body, 'application/json')
                ->send($method, $site->endpoint($route));
        } catch (ConnectionException $e) {
            throw AgentException::unreachable($e->getMessage());
        }

        $this->verify($response, $nonce, base64_decode($site->agent_public_key));

        if (! $response->successful()) {
            throw AgentException::rejected($response->status(), $response->json('error'), $response->json('message'));
        }

        return (array) $response->json();
    }

    /** Yanıt gerçekten bu siteden mi ve gerçekten BU isteğin yanıtı mı? */
    private function verify(Response $response, string $nonce, string $agentPublicKey): void
    {
        $signature = Signer::base64UrlDecode((string) $response->header(AgentProtocol::HEADER_SIGNATURE));
        $timestamp = (string) $response->header(AgentProtocol::HEADER_TIMESTAMP);

        if ($signature === null || $signature === '' || ! ctype_digit($timestamp)) {
            // İmzasız 404: WordPress çalışıyor ama rota yok, yani eklenti etkin değil
            throw $response->status() === 404 ? AgentException::notInstalled() : AgentException::unsigned($response->status());
        }

        if (abs(now()->getTimestamp() - (int) $timestamp) > AgentProtocol::MAX_CLOCK_SKEW) {
            throw AgentException::stale();
        }

        $message = AgentProtocol::responseMessage($nonce, $response->status(), $timestamp, $response->body());

        if (! Signer::verify($signature, $message, $agentPublicKey)) {
            throw AgentException::badSignature();
        }
    }
}
