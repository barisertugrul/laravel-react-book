<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RegistrationClosedTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_registration_is_closed(): void
    {
        $this->get('/register')->assertNotFound();

        $this->post('/register', [
            'name' => 'Davetsiz Misafir',
            'email' => 'misafir@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'misafir@example.com']);
    }

    public function test_admin_adds_a_member_who_gets_a_set_password_link(): void
    {
        Notification::fake();
        $admin = User::factory()->create(['role' => Role::Admin]);

        $this->actingAs($admin)->post(route('team.store'), [
            'name' => 'Yeni Teknisyen',
            'email' => 'yeni@example.com',
            'role' => 'technician',
        ])->assertSessionHasNoErrors();

        $user = User::where('email', 'yeni@example.com')->sole();
        $this->assertSame(Role::Technician, $user->role);
        $this->assertNotNull($user->email_verified_at);
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_technicians_cannot_add_members(): void
    {
        $tech = User::factory()->create(['role' => Role::Technician]);

        $this->actingAs($tech)->post(route('team.store'), [
            'name' => 'X', 'email' => 'x@example.com', 'role' => 'admin',
        ])->assertForbidden();
    }
}
