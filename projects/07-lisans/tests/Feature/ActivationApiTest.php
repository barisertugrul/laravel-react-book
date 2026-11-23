<?php

namespace Tests\Feature;

use App\Models\Activation;
use App\Models\License;
use App\Support\LicenseKey;
use App\Support\Signer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ActivationApiTest extends TestCase
{
    use RefreshDatabase;

    private function activate(string $key, string $domain = 'ornek.com'): TestResponse
    {
        return $this->postJson(route('api.licenses.activate'), ['key' => $key, 'domain' => $domain]);
    }

    /**
     * Testte bütün istekler aynı uygulama örneğinde çalışır; Sanctum bir önceki isteğin
     * kimliğini hatırlamasın diye her token'lı istekten önce koruyucuları sıfırlıyoruz.
     */
    private function withSiteToken(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($token);
    }

    private function assertSignedByUs(TestResponse $response): void
    {
        $signature = base64_decode((string) $response->headers->get('X-License-Signature'), true);

        $this->assertTrue(Signer::verify($signature, $response->getContent(), Signer::publicKey()), 'Yanıt imzası doğrulanamadı.');
    }

    public function test_activation_returns_a_site_token_and_license_details(): void
    {
        $license = License::factory()->sites(3)->create();

        $response = $this->activate($license->key, 'https://www.Ornek.com/')
            ->assertCreated()
            ->assertJsonPath('domain', 'ornek.com')
            ->assertJsonPath('license.valid', true)
            ->assertJsonPath('license.max_sites', 3)
            ->assertJsonPath('license.sites_used', 1)
            ->assertJsonPath('license.updates_available', true)
            ->assertJsonStructure(['token', 'server_time']);

        $this->assertSignedByUs($response);
        $this->assertDatabaseHas('activations', ['license_id' => $license->id, 'domain' => 'ornek.com']);
        $this->assertSame(Activation::class, Activation::sole()->tokens()->sole()->tokenable_type);
    }

    public function test_activating_the_same_site_again_does_not_use_another_seat(): void
    {
        $license = License::factory()->create(); // tek site
        $oldToken = $this->activate($license->key, 'ornek.com')->json('token');

        // Eklenti silinip yeniden kuruldu; adres farklı yazılmış olsa da site aynı
        $newToken = $this->activate($license->key, 'http://www.ornek.com')->assertOk()->json('token');

        $this->assertSame(1, $license->activations()->count());
        $this->assertNotSame($oldToken, $newToken);
        $this->withSiteToken($oldToken)->getJson(route('api.licenses.check'))->assertUnauthorized();
        $this->withSiteToken($newToken)->getJson(route('api.licenses.check'))->assertOk();
    }

    public function test_site_limit_is_enforced(): void
    {
        $license = License::factory()->sites(2)->create();

        $this->activate($license->key, 'bir.com')->assertCreated();
        $this->activate($license->key, 'iki.com')->assertCreated();
        $this->activate($license->key, 'uc.com')
            ->assertStatus(409)
            ->assertJsonPath('error', 'site_limit_reached');

        $this->assertSame(2, $license->activations()->count());
        $this->assertDatabaseHas('license_events', ['license_id' => $license->id, 'type' => 'rejected', 'domain' => 'uc.com']);
    }

    public function test_unlimited_license_has_no_site_limit(): void
    {
        $license = License::factory()->sites(null)->create();

        foreach (range(1, 6) as $i) {
            $this->activate($license->key, "site{$i}.com")->assertCreated();
        }

        $this->assertSame(6, $license->activations()->count());
    }

    public function test_made_up_key_is_rejected_without_a_database_lookup(): void
    {
        $this->activate('ANH1.uydurma.anahtar')
            ->assertStatus(422)
            ->assertJsonPath('error', 'invalid_key');
    }

    public function test_correctly_signed_key_for_a_missing_license_is_not_found(): void
    {
        // İmza geçerli ama 999 numaralı lisans yok (ör. silinmiş bir test lisansı)
        $ghost = License::factory()->make(['id' => 999, 'created_at' => now()]);

        $this->activate(LicenseKey::issue($ghost))
            ->assertNotFound()
            ->assertJsonPath('error', 'license_not_found');
    }

    public function test_invalid_domain_and_missing_fields_are_rejected(): void
    {
        $license = License::factory()->create();

        $this->activate($license->key, 'ornek..com')->assertStatus(422)->assertJsonPath('error', 'invalid_domain');
        $this->postJson(route('api.licenses.activate'), [])->assertStatus(422)->assertJsonValidationErrors(['key', 'domain']);
    }

    public function test_check_updates_last_seen_and_is_signed(): void
    {
        $license = License::factory()->create();
        $token = $this->activate($license->key)->json('token');
        $this->travel(2)->hours();

        $response = $this->withSiteToken($token)->getJson(route('api.licenses.check'))
            ->assertOk()
            ->assertJsonPath('domain', 'ornek.com')
            ->assertJsonPath('license.valid', true);

        $this->assertSignedByUs($response);
        $this->assertTrue(Activation::sole()->last_seen_at->isSameMinute(now()));
    }

    public function test_check_without_token_is_unauthorized(): void
    {
        $this->getJson(route('api.licenses.check'))->assertUnauthorized();
        $this->withSiteToken('1|uydurma')->getJson(route('api.licenses.check'))->assertUnauthorized();
    }

    public function test_expired_updates_do_not_stop_the_license(): void
    {
        $license = License::factory()->create(['updates_until' => now()->subDay()]);

        // Ömür boyu lisans: güncelleme hakkı bitti ama eklenti çalışmaya devam eder
        $this->activate($license->key)
            ->assertCreated()
            ->assertJsonPath('license.valid', true)
            ->assertJsonPath('license.updates_available', false);
    }

    public function test_deactivation_frees_the_seat_and_kills_the_token(): void
    {
        $license = License::factory()->create(); // tek site
        $token = $this->activate($license->key, 'eski-site.com')->json('token');

        $this->withSiteToken($token)->postJson(route('api.licenses.deactivate'))->assertOk()->assertJsonPath('deactivated', true);

        $this->assertDatabaseCount('activations', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->withSiteToken($token)->getJson(route('api.licenses.check'))->assertUnauthorized();
        $this->activate($license->key, 'yeni-site.com')->assertCreated(); // boşalan yer kullanılabilir
    }

    public function test_activation_attempts_are_rate_limited(): void
    {
        foreach (range(1, 10) as $ignored) {
            $this->activate('ANH1.ayni.anahtar')->assertStatus(422);
        }

        // Aynı anahtarla 11. deneme: dakikada 10 sınırı
        $this->activate('ANH1.ayni.anahtar')
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertJsonPath('error', 'too_many_requests');
    }

    public function test_rate_limit_also_counts_by_ip_across_different_keys(): void
    {
        foreach (range(1, 30) as $i) {
            $this->activate("ANH1.anahtar.{$i}")->assertStatus(422);
        }

        $this->activate('ANH1.anahtar.31')->assertStatus(429);
    }
}
