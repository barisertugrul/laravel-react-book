<?php

namespace App\Actions;

use App\Agent\AgentClient;
use App\Agent\AgentException;
use App\Agent\AgentProtocol;
use App\Enums\HealthStatus;
use App\Health\HealthEvaluator;
use App\Health\SslInspector;
use App\Models\Site;
use App\Models\SiteCheck;
use App\Notifications\SiteStatusChanged;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as Http;
use Illuminate\Support\Facades\Notification;

/**
 * Bir sitenin sağlık kontrolü: dışarıdan bakış (sayfa, süre, SSL) + içeriden rapor (ajan).
 * Ajans bağlamında çalışmalıdır (CheckSiteHealth işi bağlamı kurar).
 */
class RunHealthCheck
{
    public function __construct(
        private Http $http,
        private AgentClient $agent,
        private SslInspector $ssl,
        private HealthEvaluator $evaluator,
    ) {}

    public function handle(Site $site): SiteCheck
    {
        // 1. Ziyaretçinin gördüğü: ana sayfa açılıyor mu, ne kadar sürede?
        [$httpStatus, $responseMs, $probeError] = $this->probe($site->url);

        // 2. Sertifika ne zaman bitiyor? (yalnızca https)
        $sslExpiresAt = str_starts_with($site->url, 'https://') ? $this->ssl->expiresAt($site->url) : null;

        // 3. Ajanın raporu: sürümler, güncellemeler, yapılandırma
        $report = null;
        $agentError = null;
        if ($probeError === null) {
            try {
                $report = $this->agent->send($site, 'GET', AgentProtocol::ROUTE_HEALTH);
            } catch (AgentException $e) {
                $agentError = $e->getMessage();
            }
        }

        $result = $this->evaluator->evaluate($site->url, $httpStatus, $responseMs, $probeError, $sslExpiresAt, $report, $agentError);

        $check = $site->checks()->create([
            'status' => $result->status,
            'http_status' => $httpStatus,
            'response_ms' => $responseMs,
            'ssl_expires_at' => $sslExpiresAt,
            'issues' => $result->issues,
            'report' => $report,
            'error' => $probeError ?? $agentError,
            'checked_at' => now(),
        ]);

        $previous = $site->health_status;
        $changed = $previous !== $result->status;

        // Listeler site_checks'e inmesin diye özet sitenin kendisinde de tutulur
        $site->forceFill([
            'health_status' => $result->status,
            'status_changed_at' => $changed ? now() : $site->status_changed_at,
            'last_checked_at' => $check->checked_at,
            'response_ms' => $responseMs,
            'ssl_expires_at' => $sslExpiresAt,
            // Rapor alınamadıysa son bilinen sürümler kalsın
            'wp_version' => $report['wp_version'] ?? $site->wp_version,
            'php_version' => $report['php_version'] ?? $site->php_version,
            'agent_version' => $report['agent_version'] ?? $site->agent_version,
            'pending_updates' => $report === null ? $site->pending_updates : $this->countUpdates($report),
        ])->save();

        // İlk kontrol ("bilinmiyor" → x) bildirim değildir; sonraki her durum değişikliği bildirilir
        if ($changed && $previous !== HealthStatus::Unknown) {
            Notification::send($site->agency->users, new SiteStatusChanged($site, $previous, $check));
        }

        return $check;
    }

    /** @return array{0: int|null, 1: int|null, 2: string|null} */
    private function probe(string $url): array
    {
        $started = hrtime(true);

        try {
            $response = $this->http->timeout(10)->withUserAgent('Kumanda/1.0 (+saglik-kontrolu)')->get($url);
        } catch (ConnectionException $e) {
            return [null, null, $e->getMessage()];
        }

        return [$response->status(), (int) round((hrtime(true) - $started) / 1_000_000), null];
    }

    /** @param array<string, mixed> $report */
    private function countUpdates(array $report): int
    {
        $count = empty($report['core_update']) ? 0 : 1;

        foreach (['plugins', 'themes'] as $kind) {
            foreach ((array) ($report[$kind] ?? []) as $item) {
                $count += empty($item['update']) ? 0 : 1;
            }
        }

        return $count;
    }
}
