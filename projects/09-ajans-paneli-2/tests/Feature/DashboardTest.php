<?php

namespace Tests\Feature;

use App\Enums\HealthStatus;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page()
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_users_without_an_agency_are_asked_to_create_one()
    {
        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertRedirect(route('agencies.create'));
    }

    public function test_dashboard_lists_sites_with_problems_first_and_counts_them()
    {
        $user = $this->owner();
        $agency = $user->currentAgency;
        Site::factory()->forAgency($agency)->create(['name' => 'Sağlam'])->forceFill(['health_status' => HealthStatus::Ok])->save();
        Site::factory()->forAgency($agency)->create(['name' => 'Bozuk'])->forceFill(['health_status' => HealthStatus::Critical])->save();

        $this->actingAs($user)->get(route('dashboard'))->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->where('sites.0.name', 'Bozuk')
            ->where('sites.1.name', 'Sağlam')
            ->where('counts.critical', 1)
            ->where('counts.ok', 1));
    }
}
