<?php

namespace Tests\Feature\Auth;

use App\Enums\AgencyRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::registration());
    }

    public function test_registration_screen_can_be_rendered()
    {
        $response = $this->get(route('register'));

        $response->assertOk();
    }

    public function test_new_users_can_register()
    {
        $response = $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'agency' => 'Mavi Pixel Dijital',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_registering_creates_an_agency_owned_by_the_user()
    {
        $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'agency' => 'Mavi Pixel Dijital',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $user = User::firstWhere('email', 'test@example.com');
        $this->assertSame('Mavi Pixel Dijital', $user->currentAgency->name);
        $this->assertSame(AgencyRole::Owner, $user->roleIn($user->currentAgency));
    }

    public function test_agency_name_is_required()
    {
        $this->post(route('register.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors('agency');

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('agencies', 0);
    }
}
