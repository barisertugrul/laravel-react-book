<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

/**
 * Müşteriler kayıt sayfasından hesap açar; yönetici hesabı ise yalnızca komut satırından açılır:
 *   php artisan user:create
 */
#[Signature('user:create')]
#[Description('Yeni bir yönetici veya müşteri hesabı oluşturur')]
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

        $role = select('Rol', ['admin' => 'Yönetici', 'customer' => 'Müşteri'], default: 'admin');

        User::forceCreate([
            'role' => Role::from($role),
            'name' => $name,
            'email' => $email,
            'password' => $password,       // modeldeki 'hashed' dönüşümü şifreyi kendisi şifreler
            'email_verified_at' => now(),  // komut satırından açılan hesap doğrulanmış sayılır
        ]);

        $this->info("{$email} oluşturuldu. Artık /login adresinden giriş yapabilirsiniz.");

        return self::SUCCESS;
    }
}
