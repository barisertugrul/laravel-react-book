<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pulse tüm ajansların verisini gösterir: yalnızca PULSE_ADMINS'teki e-postalar görebilir.
 */
class PulseAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['pulse.admins' => ['yonetici@kumanda.test']]);
    }

    public function test_guests_cannot_open_pulse(): void
    {
        $this->get('/pulse')->assertForbidden();
    }

    public function test_an_agency_owner_who_is_not_a_platform_admin_is_forbidden(): void
    {
        $owner = User::factory()->create(['email' => 'sahip@ajans.test']);

        $this->actingAs($owner)->get('/pulse')->assertForbidden();
    }

    public function test_platform_admin_can_open_pulse(): void
    {
        $admin = User::factory()->create(['email' => 'Yonetici@Kumanda.test']); // büyük/küçük harf fark etmez

        $this->actingAs($admin)->get('/pulse')->assertOk();
    }
}
