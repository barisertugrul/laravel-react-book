<?php

namespace Tests\Feature;

use App\Actions\PairSite;
use App\Agent\AgentClient;
use App\Agent\AgentException;
use App\Agent\AgentProtocol;
use App\Agent\ConnectionKey;
use App\Http\Middleware\SetCurrentAgency;
use App\Models\Site;
use App\Support\Signer;
use App\Tenancy\CurrentAgency;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeAgent;
use Tests\TestCase;

/** Bölüm 8'in güvenlik incelemesinin testleri (Bölüm 8'deki SecurityFixesTest, yeni adıyla) */
class TenancyAndReplaySecurityTest extends TestCase
{
    use RefreshDatabase;

    /*
     * 1. hata: ajans bağlamı route model binding'den SONRA kuruluyordu ve scope "bağlam yoksa
     * filtreleme" diyordu. {site} filtresiz çözülüyor, başkasının sitesi açılıyordu.
     */

    public function test_without_a_context_the_old_scope_would_have_returned_every_agencys_sites(): void
    {
        $theirs = Site::factory()->create();

        // İlk sürümün scope'u bağlam yokken HİÇBİR koşul eklemiyordu; yani sorgu tam olarak buydu:
        $this->assertTrue(Site::acrossAgencies()->whereKey($theirs->id)->exists());

        // Düzeltilmiş scope: bağlam yoksa hiçbir kayıt
        $this->assertNull(Site::find($theirs->id));
    }

    public function test_the_agency_is_known_before_route_parameters_are_resolved(): void
    {
        $ali = $this->owner('Mavi Pixel');
        $mine = Site::factory()->forAgency($ali->currentAgency)->create();
        $theirs = Site::factory()->create();

        // Middleware sırası yanlış olsaydı ilk istek 404 olurdu (kapalı scope + bağlam yok),
        // yalnızca scope açık olsaydı ikinci istek 200 olurdu (sızıntı).
        $this->actingAs($ali)->get(route('sites.show', $mine))->assertOk();
        $this->actingAs($ali)->get(route('sites.show', $theirs))->assertNotFound();
    }

    public function test_the_context_middleware_runs_before_substitute_bindings(): void
    {
        // HTTP Kernel, kurulurken middleware gruplarını ve öncelik listesini router'a aktarır;
        // testte henüz istek yapılmadığı için onu kendimiz oluşturuyoruz
        app(Kernel::class);

        $middleware = app('router')->gatherRouteMiddleware(app('router')->getRoutes()->getByName('sites.show'));

        $this->assertContains(SetCurrentAgency::class, $middleware);
        $this->assertLessThan(
            array_search(SubstituteBindings::class, $middleware),
            array_search(SetCurrentAgency::class, $middleware),
        );
    }

    /*
     * 2. hata: yanıt imzası isteğe bağlı değildi (nonce yoktu). Araya giren biri, site
     * sağlıklıyken yakaladığı imzalı yanıtı sonra her kontrolde geri oynatabiliyordu:
     * site hacklense bile panel "sağlıklı" görürdü.
     */

    public function test_a_replayed_response_from_an_earlier_request_is_rejected(): void
    {
        $agency = $this->owner()->currentAgency;
        $this->actAsAgency($agency);
        $agent = (new FakeAgent)->fake();
        $site = app(PairSite::class)->handle('Lale Kafe', ConnectionKey::parse($agent->connectionKey()));

        // Saldırgan, sağlıklı bir anda geçen imzalı yanıtı kaydetmiş: o isteğin nonce'una bağlı
        $captured = $agent->respond(str_repeat('0', 32), 200, FakeAgent::healthyReport())->wait();

        $agent->intercept = fn (Request $request) => Http::response(
            (string) $captured->getBody(), 200, $captured->getHeaders(),
        );

        $this->expectExceptionObject(AgentException::badSignature());
        app(AgentClient::class)->send($site, 'GET', AgentProtocol::ROUTE_HEALTH);
    }

    public function test_the_old_response_signature_without_nonce_would_have_verified_for_any_request(): void
    {
        [$secret, $public] = Signer::generateKeyPair();
        $body = json_encode(FakeAgent::healthyReport());
        $ts = (string) time();

        // İlk sürümün yanıt metni: yalnızca durum + gövde; nonce da zaman damgası da YOK
        $oldMessage = fn () => implode("\n", ['AA1-RES', '200', hash('sha256', $body)]);
        $signature = Signer::sign($oldMessage(), $secret);

        // Hangi istek için doğrularsak doğrulayalım metin aynı: imza her isteğe "uyar"
        $this->assertTrue(Signer::verify($signature, $oldMessage(), $public));

        // Yeni metin isteğin nonce'unu içerir: başka bir isteğin yanıtı tutmaz
        $this->assertFalse(Signer::verify(
            Signer::sign(AgentProtocol::responseMessage('istek-1', 200, $ts, $body), $secret),
            AgentProtocol::responseMessage('istek-2', 200, $ts, $body),
            $public,
        ));
    }

    public function test_an_old_response_is_rejected_by_its_timestamp(): void
    {
        $agency = $this->owner()->currentAgency;
        app(CurrentAgency::class)->set($agency);
        $agent = (new FakeAgent)->fake();
        $site = app(PairSite::class)->handle('Lale Kafe', ConnectionKey::parse($agent->connectionKey()));

        $this->travel(-(AgentProtocol::MAX_CLOCK_SKEW + 60))->seconds(); // panelin saati geride (ya da yanıt çok eski)

        try {
            app(AgentClient::class)->send($site, 'GET', AgentProtocol::ROUTE_HEALTH);
            $this->fail('Eski yanıt kabul edildi.');
        } catch (AgentException $e) {
            // Saat farkında eklenti de isteği reddeder; panel imzalı 401'i ya da zaman damgasını yakalar
            $this->assertSame('stale', $e->reason);
        }
    }
}
