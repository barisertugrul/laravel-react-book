<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * Kayıt sayfası kapalı olduğu için yönetici hesabını komut satırından açıyoruz:
 *   php artisan user:create
 */
#[Signature('user:create')]
#[Description('Panele giriş yapabilecek yeni bir kullanıcı oluşturur')]
class CreateUser extends Command
{
    public function handle(): int
    {
        // Laravel Prompts: etkileşimli, doğrulamalı komut satırı soruları
        $name = text('Ad soyad', required: true);
        $email = text('E-posta', required: true, validate: fn (string $value) => Validator::make(
            ['email' => $value],
            ['email' => ['email', 'unique:users,email']],
        )->errors()->first('email') ?: null);
        $password = password('Şifre (en az 8 karakter)', required: true, validate: fn (string $value) => strlen($value) < 8 ? 'Şifre en az 8 karakter olmalı.' : null);

        User::forceCreate([
            'name' => $name,
            'email' => $email,
            'password' => $password,       // modeldeki 'hashed' dönüşümü şifreyi kendisi şifreler
            'email_verified_at' => now(),  // komut satırından açılan hesap doğrulanmış sayılır
        ]);

        $this->info("{$email} oluşturuldu. Artık /login adresinden giriş yapabilirsiniz.");

        return self::SUCCESS;
    }
}
