<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Support\Signer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\FakeAgent;
use Tests\TestCase;

class PairingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_site_is_paired_with_its_connection_key(): void
    {
        $user = $this->owner();
        $agent = (new FakeAgent)->fake();

        $response = $this->actingAs($user)->post(route('sites.store'), ['name' => 'Lale Kafe', 'connection_key' => $agent->connectionKey()]);

        $site = Site::acrossAgencies()->sole();
        $response->assertRedirect(route('sites.show', $site));

        $this->assertSame($user->current_agency_id, $site->agency_id);
        $this->assertSame('https://lale-kafe.test', $site->url);
        $this->assertSame(base64_encode($agent->publicKey), $site->agent_public_key);

        // Eklenti, panelin bu siteye özel açık anahtarını aldı; gizli anahtar ona karşılık geliyor
        $this->assertSame(
            sodium_crypto_sign_publickey_from_secretkey(base64_decode($site->panel_secret_key)),
            $agent->panelPublicKey,
        );
        $this->assertSame((string) $site->id, $agent->siteId);
    }

    public function test_the_panel_secret_key_is_encrypted_at_rest(): void
    {
        $agent = (new FakeAgent)->fake();
        $this->actingAs($this->owner())->post(route('sites.store'), ['name' => 'Lale Kafe', 'connection_key' => $agent->connectionKey()]);

        $raw = DB::table('sites')->value('panel_secret_key');
        $site = Site::acrossAgencies()->sole();

        $this->assertNotSame($site->panel_secret_key, $raw);
        $this->assertStringNotContainsString($site->panel_secret_key, $raw);
    }

    public function test_the_first_health_check_runs_right_after_pairing(): void
    {
        $agent = (new FakeAgent)->fake();
        $this->actingAs($this->owner())->post(route('sites.store'), ['name' => 'Lale Kafe', 'connection_key' => $agent->connectionKey()]);

        $site = Site::acrossAgencies()->sole();
        $this->assertSame('ok', $site->health_status->value);
        $this->assertSame('7.1.5', $site->wp_version);
    }

    public function test_garbage_is_not_a_connection_key(): void
    {
        $this->actingAs($this->owner())
            ->post(route('sites.store'), ['name' => 'Lale Kafe', 'connection_key' => 'merhaba'])
            ->assertSessionHasErrors('connection_key');

        $this->assertSame(0, Site::acrossAgencies()->count());
    }

    public function test_non_http_addresses_are_rejected(): void
    {
        [, $public] = Signer::generateKeyPair();
        $key = 'AA1.'.Signer::base64UrlEncode(json_encode(['u' => 'file:///etc', 'a' => 'file:///etc/', 't' => str_repeat('a', 48), 'k' => base64_encode($public)]));

        $this->actingAs($this->owner())
            ->post(route('sites.store'), ['name' => 'X', 'connection_key' => $key])
            ->assertSessionHasErrors('connection_key');
    }

    public function test_the_same_site_cannot_be_added_twice_to_an_agency(): void
    {
        $user = $this->owner();
        Site::factory()->forAgency($user->currentAgency)->create(['url' => 'https://lale-kafe.test']);
        $agent = (new FakeAgent)->fake();

        $this->actingAs($user)
            ->post(route('sites.store'), ['name' => 'Lale Kafe', 'connection_key' => $agent->connectionKey()])
            ->assertSessionHasErrors('connection_key');
    }

    public function test_another_agency_can_add_the_same_site(): void
    {
        Site::factory()->create(['url' => 'https://lale-kafe.test']); // başka bir ajansta
        $agent = (new FakeAgent)->fake();

        $this->actingAs($this->owner())
            ->post(route('sites.store'), ['name' => 'Lale Kafe', 'connection_key' => $agent->connectionKey()])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Site::acrossAgencies()->count());
    }

    public function test_a_used_token_is_rejected_and_no_half_paired_site_remains(): void
    {
        $agent = (new FakeAgent)->fake();
        $key = $agent->connectionKey();
        $agent->token = bin2hex(random_bytes(24)); // jeton başka bir panelde kullanılmış

        $this->actingAs($this->owner())
            ->post(route('sites.store'), ['name' => 'Lale Kafe', 'connection_key' => $key])
            ->assertSessionHasErrors(['connection_key' => 'Bağlantı anahtarı geçersiz, kullanılmış ya da süresi dolmuş.']);

        $this->assertSame(0, Site::acrossAgencies()->count());
    }

    public function test_signed_requests_do_not_follow_redirects(): void
    {
        // Ör. sitenin önündeki bir yönlendirme: takip edilseydi POST, GET'e dönüşürdü
        $agent = (new FakeAgent)->fake();
        $agent->redirectTo = 'https://lale-kafe.test/wp-login.php';

        $this->actingAs($this->owner())
            ->post(route('sites.store'), ['name' => 'Lale Kafe', 'connection_key' => $agent->connectionKey()])
            ->assertSessionHasErrors(['connection_key' => 'Yanıt imzasız geldi (HTTP 302); araya giren bir sunucu ya da eklenti hatası olabilir.']);

        $this->assertCount(1, $agent->requests); // yalnızca ilk istek; yönlendirme izlenmedi
    }

    public function test_an_impostor_with_a_different_key_is_rejected(): void
    {
        // Bağlantı anahtarı A'nın açık anahtarını taşıyor ama adreste B yanıt veriyor
        $real = new FakeAgent;
        $impostor = (new FakeAgent)->fake();
        $impostor->token = $real->token;

        $this->actingAs($this->owner())
            ->post(route('sites.store'), ['name' => 'Lale Kafe', 'connection_key' => $real->connectionKey()])
            ->assertSessionHasErrors(['connection_key' => 'Yanıtın imzası doğrulanamadı.']);

        $this->assertSame(0, Site::acrossAgencies()->count());
    }
}
