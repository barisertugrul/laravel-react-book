<?php

namespace Tests\Feature;

use App\Actions\PairSite;
use App\Actions\RunHealthCheck;
use App\Agent\ConnectionKey;
use App\Enums\AgencyRole;
use App\Enums\HealthStatus;
use App\Health\SslInspector;
use App\Jobs\CheckSiteHealth;
use App\Models\Agency;
use App\Models\Site;
use App\Models\User;
use App\Notifications\SiteStatusChanged;
use App\Tenancy\CurrentAgency;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Support\FakeAgent;
use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    private Agency $agency;

    private FakeAgent $agent;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        // Sertifika okuma gerçek ağa çıkar; testte 90 gün sonra biten bir sertifika varsayıyoruz
        $this->sslExpiresIn(90);

        $this->agency = $this->owner()->currentAgency;
        $this->actAsAgency($this->agency);
        $this->agent = (new FakeAgent)->fake();
        $this->site = app(PairSite::class)->handle('Lale Kafe', ConnectionKey::parse($this->agent->connectionKey()));
    }

    private function sslExpiresIn(int $days): void
    {
        $this->app->instance(SslInspector::class, new class($days) extends SslInspector
        {
            public function __construct(private int $days) {}

            public function expiresAt(string $url): ?CarbonImmutable
            {
                return CarbonImmutable::now()->addDays($this->days);
            }
        });
    }

    private function check(): \App\Models\SiteCheck
    {
        return app(RunHealthCheck::class)->handle($this->site->refresh());
    }

    public function test_a_healthy_site(): void
    {
        $check = $this->check();

        $this->assertSame(HealthStatus::Ok, $check->status);
        $this->assertSame([], $check->issues);
        $this->assertSame(200, $check->http_status);
        $this->assertSame('7.1.5', $this->site->fresh()->wp_version);
        $this->assertSame('Contact Form 7', $check->report['plugins'][1]['name']);
    }

    public function test_pending_updates_are_a_warning(): void
    {
        $this->agent->report['core_update'] = '7.2';
        $this->agent->report['plugins'][1]['update'] = '6.2';

        $check = $this->check();

        $this->assertSame(HealthStatus::Warning, $check->status);
        $this->assertEqualsCanonicalizing(['core_update', 'plugin_updates'], array_column($check->issues, 'code'));
        $this->assertSame(2, $this->site->fresh()->pending_updates);
    }

    public function test_debug_output_visible_to_visitors_is_critical(): void
    {
        $this->agent->report['debug'] = true;
        $this->agent->report['debug_display'] = true;

        $this->assertSame(HealthStatus::Critical, $this->check()->status);
    }

    public function test_an_unreachable_site_is_critical_and_keeps_its_last_known_versions(): void
    {
        $this->check();
        $this->agent->homepageStatus = null;

        $check = $this->check();

        $this->assertSame(HealthStatus::Critical, $check->status);
        $this->assertSame('unreachable', $check->issues[0]['code']);
        $this->assertSame('7.1.5', $this->site->fresh()->wp_version);
    }

    public function test_a_removed_plugin_is_reported(): void
    {
        $this->agent->uninstalled = true;

        $check = $this->check();

        $this->assertSame(HealthStatus::Critical, $check->status);
        $this->assertStringContainsString('eklentisi bulunamadı', $check->error);
    }

    public function test_an_expiring_certificate_is_a_warning_then_critical(): void
    {
        $this->sslExpiresIn(10);
        $this->assertSame(HealthStatus::Warning, $this->check()->status);

        $this->sslExpiresIn(2);
        $this->assertSame(HealthStatus::Critical, $this->check()->status);
    }

    public function test_status_changes_are_notified_to_the_whole_team_but_not_the_first_check(): void
    {
        Notification::fake();
        $member = User::factory()->create();
        $this->agency->addMember($member, AgencyRole::Member);

        $this->check(); // bilinmiyor → sağlıklı: bildirim yok
        Notification::assertNothingSent();

        $this->agent->homepageStatus = 503;
        $this->check(); // sağlıklı → kritik
        Notification::assertSentTo([$member, ...$this->agency->users], SiteStatusChanged::class);
        Notification::assertSentTimes(SiteStatusChanged::class, 2);

        $this->check(); // kritik → kritik: yeni bildirim yok
        Notification::assertSentTimes(SiteStatusChanged::class, 2);
    }

    public function test_the_job_sets_up_the_agency_context_itself(): void
    {
        // Kuyruk işçisinde middleware yok: bağlam boş başlar
        app(CurrentAgency::class)->set(null);

        CheckSiteHealth::dispatchSync($this->site);

        $this->actAsAgency($this->agency);
        $this->assertSame(1, $this->site->checks()->count());
        $this->assertSame($this->agency->id, $this->site->checks()->first()->agency_id);
    }

    public function test_the_scheduler_command_queues_sites_of_every_agency(): void
    {
        Queue::fake();
        Site::factory()->count(2)->create(); // başka ajansların siteleri
        app(CurrentAgency::class)->set(null);

        $this->artisan('sites:check')->expectsOutput('3 site kuyruğa kondu.')->assertSuccessful();

        Queue::assertPushed(CheckSiteHealth::class, 3);
    }

    public function test_check_now_button_queues_a_check(): void
    {
        Queue::fake();

        $this->actingAs($this->agency->users->first())
            ->post(route('sites.check', $this->site))
            ->assertRedirect();

        Queue::assertPushed(CheckSiteHealth::class, fn ($job) => $job->site->is($this->site));
    }

    public function test_every_request_uses_a_fresh_nonce(): void
    {
        $this->check();
        $this->check();

        $nonces = array_map(fn ($r) => $r->header('X-Ajans-Nonce')[0], $this->agent->requests);
        $this->assertCount(3, $nonces); // eşleştirme + 2 kontrol
        $this->assertCount(3, array_unique($nonces));
    }
}
