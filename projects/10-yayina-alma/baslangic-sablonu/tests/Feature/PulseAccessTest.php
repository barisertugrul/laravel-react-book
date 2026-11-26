<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pulse uygulamanın tüm verisini gösterir: yalnızca PULSE_ADMINS'teki e-postalar görebilir.
 */
class PulseAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['pulse.admins' => ['yonetici@uygulama.test']]);
    }

    public function test_guests_cannot_open_pulse(): void
    {
        $this->get('/pulse')->assertForbidden();
    }

    public function test_a_signed_in_user_who_is_not_an_admin_is_forbidden(): void
    {
        $user = User::factory()->create(['email' => 'personel@uygulama.test']);

        $this->actingAs($user)->get('/pulse')->assertForbidden();
    }

    public function test_an_admin_can_open_pulse(): void
    {
        $admin = User::factory()->create(['email' => 'Yonetici@Uygulama.test']); // büyük/küçük harf fark etmez

        $this->actingAs($admin)->get('/pulse')->assertOk();
    }
}
