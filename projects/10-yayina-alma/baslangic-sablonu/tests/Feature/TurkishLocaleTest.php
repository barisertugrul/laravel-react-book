<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TurkishLocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_failed_login_message_is_turkish(): void
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'yanlis-parola',
        ])->assertSessionHasErrors([
            'email' => 'Bu bilgilerle eşleşen bir hesap bulunamadı.',
        ]);
    }

    public function test_password_reset_link_status_and_email_are_turkish(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status', 'Parola sıfırlama bağlantısını e-posta adresinize gönderdik.');

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user) {
            $mail = $notification->toMail($user);

            return $mail->subject === 'Parolanızı sıfırlayın'
                && $mail->actionText === 'Parolayı sıfırla';
        });
    }
}
