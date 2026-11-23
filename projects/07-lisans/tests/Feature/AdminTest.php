<?php

namespace Tests\Feature;

use App\Enums\LicenseStatus;
use App\Enums\Role;
use App\Models\License;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => Role::Admin]);
    }

    public function test_customers_cannot_open_the_admin_panel(): void
    {
        $license = License::factory()->create();

        $this->actingAs($license->user)->get(route('admin.licenses.index'))->assertForbidden();
        $this->post(route('admin.licenses.revoke', $license), ['reason' => 'x'])->assertForbidden();
    }

    public function test_admin_can_search_licenses_by_email(): void
    {
        License::factory()->for(User::factory()->create(['email' => 'aranan@example.com']))->create();
        License::factory()->create();

        $this->actingAs($this->admin())->get(route('admin.licenses.index', ['q' => 'aranan']))
            ->assertInertia(fn (Assert $page) => $page->has('licenses.data', 1));
    }

    public function test_admin_detail_page_does_not_expose_the_key(): void
    {
        $license = License::factory()->create();

        $this->actingAs($this->admin())->get(route('admin.licenses.show', $license))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('admin/licenses/show')->missing('license.key'));
    }

    public function test_revoking_needs_a_reason_and_is_logged(): void
    {
        $license = License::factory()->create();
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.licenses.revoke', $license), ['reason' => ''])->assertSessionHasErrors('reason');
        $this->actingAs($admin)->post(route('admin.licenses.revoke', $license), ['reason' => 'İade'])->assertRedirect();

        $license->refresh();
        $this->assertSame(LicenseStatus::Revoked, $license->status);
        $this->assertSame('İade', $license->revoke_reason);
        $this->assertDatabaseHas('license_events', ['license_id' => $license->id, 'type' => 'revoked']);
    }

    public function test_extending_adds_twelve_months_to_the_current_end_date(): void
    {
        $license = License::factory()->create(['updates_until' => now()->addMonths(2)]);

        $this->actingAs($this->admin())->post(route('admin.licenses.extend', $license));

        $this->assertSame(now()->addMonths(14)->toDateString(), $license->fresh()->updates_until->toDateString());
    }

    public function test_extending_an_expired_license_starts_from_today(): void
    {
        $license = License::factory()->create(['updates_until' => now()->subMonths(3)]);

        $this->actingAs($this->admin())->post(route('admin.licenses.extend', $license));

        $this->assertSame(now()->addMonths(12)->toDateString(), $license->fresh()->updates_until->toDateString());
    }
}
