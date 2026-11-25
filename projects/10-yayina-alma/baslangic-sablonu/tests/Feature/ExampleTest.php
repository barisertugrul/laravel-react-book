<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Kök adres: başlangıç kitinin örnek sayfası yerine panel (Bölüm 10) */
class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_redirects_to_the_dashboard(): void
    {
        $this->get(route('home'))->assertRedirect('/dashboard');
    }

    public function test_guests_end_up_on_the_login_page(): void
    {
        $this->followingRedirects()->get(route('home'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('auth/login'));
    }

    public function test_signed_in_users_end_up_on_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create())->followingRedirects()->get(route('home'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('dashboard'));
    }

    public function test_the_starter_kit_welcome_page_is_gone(): void
    {
        $this->assertFileDoesNotExist(resource_path('js/pages/welcome.tsx'));
    }
}
