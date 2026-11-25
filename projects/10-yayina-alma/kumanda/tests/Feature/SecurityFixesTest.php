<?php

namespace Tests\Feature;

use App\Actions\PairSite;
use App\Agent\AgentProtocol;
use App\Agent\ConnectionKey;
use App\Models\Agency;
use App\Models\Package;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Testing\TestResponse;
use Tests\Support\FakeAgent;
use Tests\TestCase;

/** Bölüm 9'un "Güvenlik incelemesi"nin testleri */
class SecurityFixesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Yayın kanalı izni gerçek bir sürücüyle denenir: testlerde BROADCAST_CONNECTION=null'dır ve
     * null sürücüsü izin fonksiyonunu HİÇ çağırmaz (her kanala "tamam" der). Reverb sürücüsüne geçip
     * kanalları ona yeniden kaydediyoruz; sunucuya bağlanılmaz, izin imzası yerelde üretilir.
     */
    private function useReverbDriver(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);
        Broadcast::purge('reverb');
        require base_path('routes/channels.php');
    }

    /** Echo'nun yaptığı istek: "bu kullanıcı bu özel kanala abone olabilir mi?" */
    private function subscribe(User $user, Agency $agency): TestResponse
    {
        return $this->actingAs($user)->post('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => "private-agencies.{$agency->id}",
        ]);
    }

    /*
     * 1. hata: ajans kanalının izni yalnızca "kullanıcı BİR ajansın üyesi mi?" diye soruyordu:
     *
     *   Broadcast::channel('agencies.{agency}', fn (User $user, Agency $agency) => $user->agencies()->exists());
     *
     * Kumanda'da her kullanıcı en az bir ajansın üyesidir (kayıt olan kendi ajansını açar). Başka bir
     * ajansın numarasıyla abone olan herkes onun toplu işlemlerini, site adlarını ve hatalarını canlı izliyordu.
     */
    public function test_the_old_channel_check_would_have_let_any_user_in(): void
    {
        $stranger = $this->owner('Kuzey Web');
        $victim = $this->owner('Mavi Pixel')->currentAgency;

        // İlk sürümün sorusu: "bir ajansın üyesi mi?" Evet, ama YANLIŞ ajansın.
        $this->assertTrue($stranger->agencies()->exists());
        $this->assertFalse($stranger->belongsToAgency($victim));
    }

    public function test_only_members_can_subscribe_to_an_agency_channel(): void
    {
        $this->useReverbDriver();
        $owner = $this->owner('Mavi Pixel');
        $agency = $owner->currentAgency;

        $this->subscribe($owner, $agency)->assertOk()->assertJsonStructure(['auth']);
        $this->subscribe($this->owner('Kuzey Web'), $agency)->assertForbidden();
    }

    public function test_a_guest_cannot_subscribe(): void
    {
        $this->useReverbDriver();
        $agency = $this->owner()->currentAgency;

        $this->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-agencies.{$agency->id}"])
            ->assertForbidden();
    }

    /*
     * 2. hata: paket indirme adresi SÜRESİZ imzalıydı:
     *
     *   URL::signedRoute('packages.download', ['package' => $package->id, 'site' => $site->id]);
     *
     * Adres eklentinin günlüklerine, sitenin erişim kayıtlarına düşer; müşteri onu görebilir ve
     * paylaşabilir. Süresiz imza, ücretli bir eklentinin zip'ini sonsuza kadar herkese açık yapar.
     */
    public function test_the_old_url_without_an_expiry_would_still_work_a_year_later(): void
    {
        [$package, $site] = $this->packageAndSite();

        $forever = URL::signedRoute('packages.download', ['package' => $package->id, 'site' => $site->id]);
        $this->travel(365)->days();

        $this->get($forever)->assertOk(); // imza hâlâ geçerli: hatanın kendisi
    }

    public function test_the_download_url_sent_to_the_site_expires_after_ten_minutes(): void
    {
        $agent = (new FakeAgent('https://lale-kafe.test'))->fake();
        [$package] = $this->packageAndSite();
        $site = app(PairSite::class)->handle('Lale Kafe', ConnectionKey::parse($agent->connectionKey()));

        $this->actingAs($this->user)->post(route('operations.store'), [
            'type' => 'install', 'source' => 'package', 'package_id' => $package->id, 'site_ids' => [$site->id],
        ])->assertSessionHasNoErrors();

        $url = collect($agent->commands)->firstWhere(0, AgentProtocol::ROUTE_PLUGIN_INSTALL)[1]['url'];
        $this->assertStringContainsString('expires=', $url);

        $this->travel(9)->minutes();
        $this->get($url)->assertOk();

        $this->travel(2)->minutes();
        $this->get($url)->assertForbidden();
    }

    private User $user;

    /** @return array{0: Package, 1: Site} */
    private function packageAndSite(): array
    {
        Storage::fake('local');
        $this->user = $this->owner();
        $agency = $this->user->currentAgency;
        $this->actAsAgency($agency);

        Storage::disk('local')->put('packages/form-kalesi.zip', 'zip içeriği');
        $package = Package::create([
            'slug' => 'form-kalesi', 'name' => 'Form Kalesi', 'version' => '2.5.0', 'path' => 'packages/form-kalesi.zip',
            'sha256' => hash('sha256', 'zip içeriği'), 'size' => 11, 'uploaded_by' => $this->user->id,
        ]);

        return [$package, Site::factory()->forAgency($agency)->create()];
    }
}
