<?php

namespace Tests\Unit;

use App\Enums\HealthStatus;
use App\Health\HealthEvaluator;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\FakeAgent;
use Tests\TestCase;

class HealthEvaluatorTest extends TestCase
{
    private function evaluate(array $overrides = [], string $url = 'https://ornek.test', ?int $status = 200, ?int $ms = 300, ?int $sslDays = 90): array
    {
        $result = (new HealthEvaluator)->evaluate(
            $url, $status, $ms, null,
            $sslDays === null ? null : CarbonImmutable::now()->addDays($sslDays)->addHour(),
            [...FakeAgent::healthyReport(), ...$overrides], null,
        );

        return [$result->status, array_column($result->issues, 'code')];
    }

    public function test_a_clean_report_is_ok(): void
    {
        $this->assertSame([HealthStatus::Ok, []], $this->evaluate());
    }

    /** @return array<string, array{0: array<string, mixed>, 1: HealthStatus, 2: string}> */
    public static function rules(): array
    {
        return [
            'eski PHP' => [['php_version' => '7.4.33'], HealthStatus::Warning, 'old_php'],
            'dosya düzenleme açık' => [['file_edit_disabled' => false], HealthStatus::Warning, 'file_edit'],
            'çok yönetici' => [['admin_count' => 5], HealthStatus::Warning, 'admins'],
            'WP_DEBUG' => [['debug' => true], HealthStatus::Warning, 'debug'],
            'hata ekranda' => [['debug' => true, 'debug_display' => true], HealthStatus::Critical, 'debug_display'],
            'çekirdek güncellemesi' => [['core_update' => '7.2'], HealthStatus::Warning, 'core_update'],
        ];
    }

    #[DataProvider('rules')]
    public function test_report_rules(array $report, HealthStatus $expected, string $code): void
    {
        [$status, $codes] = $this->evaluate($report);

        $this->assertSame($expected, $status);
        $this->assertContains($code, $codes);
    }

    public function test_the_worst_finding_wins(): void
    {
        [$status] = $this->evaluate(['admin_count' => 9], status: 500);

        $this->assertSame(HealthStatus::Critical, $status);
    }

    public function test_plain_http_and_slow_responses_are_warnings(): void
    {
        $this->assertSame([HealthStatus::Warning, ['no_https']], $this->evaluate(url: 'http://ornek.test', sslDays: null));
        $this->assertSame([HealthStatus::Warning, ['slow']], $this->evaluate(ms: 3500));
    }

    public function test_certificate_thresholds(): void
    {
        $this->assertSame(HealthStatus::Warning, $this->evaluate(sslDays: 14)[0]);
        $this->assertSame(HealthStatus::Critical, $this->evaluate(sslDays: 3)[0]);
        $this->assertSame(['ssl_expired'], $this->evaluate(sslDays: -2)[1]);
    }
}
