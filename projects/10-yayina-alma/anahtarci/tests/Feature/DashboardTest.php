<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_users_are_sent_to_the_right_place(): void
    {
        $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertRedirect(route('licenses.index'));
        $this->actingAs(User::factory()->create(['role' => Role::Admin]))->get(route('dashboard'))->assertRedirect(route('admin.licenses.index'));
    }
}
