<?php

namespace App\Health;

use App\Enums\HealthStatus;
use Carbon\CarbonImmutable;

/**
 * Ham ölçümleri bulgulara çevirir. Saf bir sınıf: ağ yok, veritabanı yok; kurallar
 * birim testleriyle tek tek denenebilir. En kötü bulgu sitenin durumunu belirler.
 */
class HealthEvaluator
{
    /** Bu hatalar sitenin değil anahtarların sorunudur: çözümü "Bağlantıyı yenile" */
    public const KEY_PROBLEMS = ['bad_signature', 'not_paired', 'wrong_site'];

    public const SLOW_RESPONSE_MS = 2000;

    public const SSL_WARNING_DAYS = 14;

    public const SSL_CRITICAL_DAYS = 3;

    public const MAX_ADMINS = 3;

    public const MIN_PHP = '8.1';

    /** @var list<array{level: string, code: string, message: string}> */
    private array $issues = [];

    /**
     * @param  array<string, mixed>|null  $report  ajanın raporu (alınamadıysa null)
     */
    public function evaluate(
        string $url,
        ?int $httpStatus,
        ?int $responseMs,
        ?string $probeError,
        ?CarbonImmutable $sslExpiresAt,
        ?array $report,
        ?string $agentError,
        ?string $agentErrorReason = null,
    ): HealthResult {
        $this->issues = [];

        // --- Panelin dışarıdan gördüğü: site açılıyor mu? ---
        if ($probeError !== null) {
            $this->critical('unreachable', "Site açılmıyor: {$probeError}");
        } elseif ($httpStatus !== null && $httpStatus >= 500) {
            $this->critical('server_error', "Ana sayfa HTTP {$httpStatus} döndürüyor.");
        } elseif ($httpStatus !== null && $httpStatus >= 400) {
            $this->warning('client_error', "Ana sayfa HTTP {$httpStatus} döndürüyor.");
        }

        if ($responseMs !== null && $responseMs > self::SLOW_RESPONSE_MS) {
            $this->warning('slow', "Ana sayfa yavaş: {$responseMs} ms.");
        }

        $this->checkSsl($url, $sslExpiresAt);

        // --- Ajanın içeriden bildirdiği ---
        if ($agentError !== null && in_array($agentErrorReason, self::KEY_PROBLEMS, true)) {
            // Anahtarlar artık eşleşmiyor: site yeniden kuruldu, eklenti başka panele bağlandı ya da bağlantı kesildi.
            // Siteyi silmeye gerek yok: yeni bir bağlantı anahtarıyla aynı kayıt yeniden bağlanır.
            $this->critical('agent_keys', $agentError.' Eklentiden yeni bir bağlantı anahtarı alıp site sayfasındaki “Bağlantıyı yenile” ile yeniden bağlayın.');
        } elseif ($agentError !== null) {
            $this->critical('agent', $agentError);
        } elseif ($report !== null) {
            $this->checkReport($report);
        }

        $status = HealthStatus::Ok;
        foreach ($this->issues as $issue) {
            $level = HealthStatus::from($issue['level']);
            if ($level->severity() > $status->severity()) {
                $status = $level;
            }
        }

        return new HealthResult($status, $this->issues);
    }

    private function checkSsl(string $url, ?CarbonImmutable $expiresAt): void
    {
        if (parse_url($url, PHP_URL_SCHEME) !== 'https') {
            $this->warning('no_https', 'Site HTTPS kullanmıyor.');

            return;
        }

        if ($expiresAt === null) {
            return; // okunamadı; sayfa isteği sertifika hatasını zaten yakalar
        }

        $days = (int) floor(now()->diffInDays($expiresAt, false));

        if ($days < 0) {
            $this->critical('ssl_expired', 'SSL sertifikasının süresi dolmuş.');
        } elseif ($days <= self::SSL_CRITICAL_DAYS) {
            $this->critical('ssl_expiring', "SSL sertifikası {$days} gün içinde bitiyor.");
        } elseif ($days <= self::SSL_WARNING_DAYS) {
            $this->warning('ssl_expiring', "SSL sertifikası {$days} gün içinde bitiyor.");
        }
    }

    /** @param array<string, mixed> $report */
    private function checkReport(array $report): void
    {
        if (! empty($report['core_update'])) {
            $this->warning('core_update', "WordPress {$report['core_update']} sürümü yayımlandı (kurulu: {$report['wp_version']}).");
        }

        $plugins = array_filter((array) ($report['plugins'] ?? []), fn ($p) => ! empty($p['update']));
        if ($plugins !== []) {
            $names = implode(', ', array_map(fn ($p) => $p['name'], array_slice($plugins, 0, 3)));
            $this->warning('plugin_updates', count($plugins)." eklentinin güncellemesi var: {$names}".(count($plugins) > 3 ? '…' : '.'));
        }

        $themes = array_filter((array) ($report['themes'] ?? []), fn ($t) => ! empty($t['update']));
        if ($themes !== []) {
            $this->warning('theme_updates', count($themes).' temanın güncellemesi var.');
        }

        if (isset($report['php_version']) && version_compare((string) $report['php_version'], self::MIN_PHP, '<')) {
            $this->warning('old_php', "PHP {$report['php_version']} artık güvenlik güncellemesi almıyor.");
        }

        // Hata ayıklama çıktısı ziyaretçiye görünüyorsa dosya yolları ve sorgular sızar
        if (! empty($report['debug_display'])) {
            $this->critical('debug_display', 'WP_DEBUG_DISPLAY açık: hata mesajları ziyaretçilere görünüyor.');
        } elseif (! empty($report['debug'])) {
            $this->warning('debug', 'WP_DEBUG açık.');
        }

        if (empty($report['file_edit_disabled'])) {
            $this->warning('file_edit', 'Yönetim panelinden dosya düzenleme açık (DISALLOW_FILE_EDIT).');
        }

        if (($report['admin_count'] ?? 0) > self::MAX_ADMINS) {
            $this->warning('admins', "{$report['admin_count']} yönetici hesabı var.");
        }

        // Bölüm 9: lisanssız eklenti çalışır ama güncelleme alamaz; geçersiz lisans ise bilinmeli
        $license = (array) ($report['license'] ?? []);

        if (($license['state'] ?? null) === 'invalid') {
            $this->warning('license_invalid', 'Ajans Asistanı lisansı geçersiz: '.($license['error'] ?? 'iptal edilmiş ya da bu siteden kaldırılmış.'));
        } elseif (($license['state'] ?? null) === 'active' && empty($license['updates_available'])) {
            $this->warning('license_updates', "Ajans Asistanı lisansının güncelleme süresi {$license['updates_until']} tarihinde doldu.");
        }
    }

    private function warning(string $code, string $message): void
    {
        $this->issues[] = ['level' => HealthStatus::Warning->value, 'code' => $code, 'message' => $message];
    }

    private function critical(string $code, string $message): void
    {
        $this->issues[] = ['level' => HealthStatus::Critical->value, 'code' => $code, 'message' => $message];
    }
}
