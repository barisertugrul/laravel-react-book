<?php

namespace Tests\Feature;

use App\Actions\PairSite;
use App\Actions\RunHealthCheck;
use App\Agent\ConnectionKey;
use App\Enums\HealthStatus;
use App\Models\Site;
use App\Models\User;
use App\Support\Signer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeAgent;
use Tests\TestCase;

/** Anahtar yenileme: site silinmeden, yeni bağlantı anahtarıyla aynı kayıt yeniden bağlanır */
class RepairSiteTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private FakeAgent $agent;

    private Site $site;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->owner();
        $this->actAsAgency($this->user->currentAgency);
        $this->agent = (new FakeAgent)->fake();
        $this->site = app(PairSite::class)->handle('Lale Kafe', ConnectionKey::parse($this->agent->connectionKey()));
        app(RunHealthCheck::class)->handle($this->site);
    }

    /** Eklenti silinip yeniden kuruldu: yeni anahtar çifti, panel bilgisi yok */
    private function reinstallPlugin(): void
    {
        [$this->agent->secretKey, $this->agent->publicKey] = Signer::generateKeyPair();
        $this->agent->panelPublicKey = null;
        $this->agent->siteId = null;
    }

    private function repair(string $connectionKey): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user)->put(route('sites.repair', $this->site), ['connection_key' => $connectionKey]);
    }

    public function test_a_reinstalled_plugin_is_reported_as_a_key_problem(): void
    {
        $this->reinstallPlugin();

        $check = app(RunHealthCheck::class)->handle($this->site->fresh());

        $this->assertSame(HealthStatus::Critical, $check->status);
        $this->assertSame('agent_keys', $check->issues[0]['code']);
        $this->assertStringContainsString('Bağlantıyı yenile', $check->issues[0]['message']);
    }

    public function test_the_same_site_is_repaired_and_keeps_its_history(): void
    {
        $this->reinstallPlugin();
        app(RunHealthCheck::class)->handle($this->site->fresh());
        $oldPanelKey = $this->site->fresh()->panel_secret_key;

        $this->repair($this->agent->connectionKey())->assertSessionHasNoErrors()->assertRedirect(route('sites.show', $this->site));

        $site = Site::sole();
        $this->assertSame($this->site->id, $site->id);
        $this->assertSame(base64_encode($this->agent->publicKey), $site->agent_public_key);
        $this->assertNotSame($oldPanelKey, $site->panel_secret_key, 'Panelin anahtar çifti de yenilenmeli');
        $this->assertSame((string) $site->id, $this->agent->siteId, 'Eklentiye aynı site numarası bildirilmeli');

        // Geçmiş korunur: ilk sağlıklı kontrol + anahtar sorunu + yenilemeden sonraki kontrol
        $this->assertSame(3, $site->checks()->count());
        $this->assertSame(HealthStatus::Ok, $site->health_status);
    }

    public function test_a_failed_repair_leaves_the_old_keys_in_place(): void
    {
        $before = $this->site->fresh()->only(['agent_public_key', 'panel_secret_key', 'api_url']);
        $usedKey = $this->agent->connectionKey();
        $this->repair($usedKey)->assertSessionHasNoErrors(); // jeton harcandı (eklenti yeniden kurulmadan da yenilenebilir)

        $after = $this->site->fresh()->only(['agent_public_key', 'panel_secret_key', 'api_url']);
        $this->repair($usedKey)->assertSessionHasErrors('connection_key'); // aynı jeton ikinci kez

        $this->assertSame($after, $this->site->fresh()->only(['agent_public_key', 'panel_secret_key', 'api_url']));
        $this->assertNotSame($before['panel_secret_key'], $after['panel_secret_key']);
    }

    public function test_another_sites_key_cannot_be_used(): void
    {
        $other = new FakeAgent('https://baska-site.test');

        $this->repair($other->connectionKey())
            ->assertSessionHasErrors(['connection_key' => 'Bu anahtar https://baska-site.test adresine ait; bu kayıt https://lale-kafe.test için.']);
    }

    public function test_adding_the_same_site_again_points_to_repair(): void
    {
        $this->actingAs($this->user)->post(route('sites.store'), ['name' => 'Lale Kafe', 'connection_key' => $this->agent->connectionKey()])
            ->assertSessionHasErrors(['connection_key' => 'https://lale-kafe.test zaten bu ajansa bağlı. Anahtar değiştiyse sitenin sayfasındaki “Bağlantıyı yenile” bölümünü kullanın.']);

        $this->assertSame(1, Site::count());
    }

    public function test_another_agencys_site_cannot_be_repaired(): void
    {
        $this->actingAs($this->owner('Kuzey Web'))
            ->put(route('sites.repair', $this->site), ['connection_key' => $this->agent->connectionKey()])
            ->assertNotFound();
    }
}
