<?php

namespace Tests\Feature;

use App\Enums\LicenseStatus;
use App\Models\Activation;
use App\Models\License;
use App\Support\LicenseKey;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Bölüm sonundaki "Güvenlik incelemesi"nin testleri */
class SecurityFixesTest extends TestCase
{
    use RefreshDatabase;

    /** 1. hata: site sınırını kilitsiz "say, sonra ekle" ile kontrol etmek */
    public function test_count_then_insert_lets_parallel_requests_exceed_the_limit(): void
    {
        $license = License::factory()->create(); // tek site

        // İlk sürümün yöntemi. İki site AYNI ANDA etkinleştiriyor: ikisi de önce sayar...
        $countSeenByA = $license->activations()->count();
        $countSeenByB = $license->activations()->count();

        // ...ikisi de 0 < 1 görür ve ekler
        if ($countSeenByA < $license->max_sites) {
            $license->activations()->create(['domain' => 'a.com']);
        }
        if ($countSeenByB < $license->max_sites) {
            $license->activations()->create(['domain' => 'b.com']);
        }

        $this->assertSame(2, $license->activations()->count()); // 1 sitelik lisans, 2 site!
    }

    public function test_locked_activation_counts_sequentially(): void
    {
        $license = License::factory()->create();

        // Düzeltilmiş ActivateLicense: lisans satırı kilitlenir, ikinci istek birincinin eklediğini görür.
        // (SQLite FOR UPDATE'i yok sayar ama yazmaları zaten sıraya koyar; MySQL/PostgreSQL'de kilit gerçek.)
        $this->postJson(route('api.licenses.activate'), ['key' => $license->key, 'domain' => 'a.com'])->assertCreated();
        $this->postJson(route('api.licenses.activate'), ['key' => $license->key, 'domain' => 'b.com'])->assertStatus(409);

        $this->assertSame(1, $license->activations()->count());
    }

    public function test_the_same_site_cannot_be_inserted_twice_even_in_a_race(): void
    {
        $license = License::factory()->sites(5)->create();
        $license->activations()->create(['domain' => 'a.com']);

        // Kilit bir yana, (license_id, domain) üzerindeki unique dizin son savunma hattıdır
        $this->expectException(UniqueConstraintViolationException::class);
        Activation::create(['license_id' => $license->id, 'domain' => 'a.com']);
    }

    /** 2. hata: yalnızca imzaya bakıp iptal edilmiş lisansı kabul etmek */
    public function test_signature_alone_does_not_make_a_license_valid(): void
    {
        $license = License::factory()->revoked()->create();

        // İade edilen lisansın anahtarı hâlâ kusursuz imzalı: imza "bunu biz ürettik" der, "geçerli" demez
        $this->assertNotNull(LicenseKey::parse($license->key));

        $this->postJson(route('api.licenses.activate'), ['key' => $license->key, 'domain' => 'ornek.com'])
            ->assertForbidden()
            ->assertJsonPath('error', 'license_revoked');

        $this->assertDatabaseCount('activations', 0);
        $this->assertDatabaseHas('license_events', ['license_id' => $license->id, 'type' => 'rejected']);
    }

    public function test_sites_learn_about_a_revocation_on_their_next_check(): void
    {
        $license = License::factory()->create();
        $token = $this->postJson(route('api.licenses.activate'), ['key' => $license->key, 'domain' => 'ornek.com'])->json('token');

        $license->update(['status' => LicenseStatus::Revoked, 'revoked_at' => now()]);

        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson(route('api.licenses.check'))
            ->assertOk()
            ->assertJsonPath('license.valid', false)
            ->assertJsonPath('license.status', 'revoked');
    }
}
