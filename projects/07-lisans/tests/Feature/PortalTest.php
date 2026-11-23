<?php

namespace Tests\Feature;

use App\Actions\ActivateLicense;
use App\Models\License;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Müşteri portalı: lisanslarım, anahtar, siteler */
class PortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_customers_see_only_their_own_licenses_and_no_keys_in_the_list(): void
    {
        $license = License::factory()->create();
        License::factory()->create(); // başkasının

        $this->actingAs($license->user)->get(route('licenses.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('shop/licenses/index')
                ->has('licenses', 1)
                ->where('licenses.0.id', $license->id)
                ->missing('licenses.0.key')); // #[Hidden]: anahtar listede gezinmez
    }

    public function test_owner_sees_the_key_on_the_detail_page(): void
    {
        $license = License::factory()->create();

        $this->actingAs($license->user)->get(route('licenses.show', $license))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('license.key', $license->key));
    }

    public function test_someone_elses_license_is_not_found(): void
    {
        $license = License::factory()->create();

        $this->actingAs(User::factory()->create())->get(route('licenses.show', $license))->assertNotFound();
    }

    public function test_customer_can_remove_a_site_to_free_a_seat(): void
    {
        $license = License::factory()->create();
        ['activation' => $activation] = (new ActivateLicense)->handle($license->key, 'eski-site.com');

        $this->actingAs($license->user)
            ->delete(route('licenses.activations.destroy', [$license, $activation]))
            ->assertRedirect();

        $this->assertDatabaseCount('activations', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
        $this->assertDatabaseHas('license_events', ['license_id' => $license->id, 'type' => 'removed', 'domain' => 'eski-site.com']);
    }

    public function test_sites_of_other_licenses_cannot_be_removed(): void
    {
        $mine = License::factory()->create();
        $theirs = License::factory()->create();
        ['activation' => $activation] = (new ActivateLicense)->handle($theirs->key, 'onlarin-sitesi.com');

        // Kendi lisansımın adresine başkasının aktivasyon numarasını yazıyorum
        $this->actingAs($mine->user)
            ->delete(route('licenses.activations.destroy', [$mine, $activation]))
            ->assertNotFound();

        $this->assertDatabaseCount('activations', 1);
    }
}
