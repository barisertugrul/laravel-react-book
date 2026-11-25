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
 *
 * SSH olmayan sunucuda (cron ile, soru sorulamaz) seçeneklerle (Bölüm 10):
 *   php artisan user:create --name="Ayşe Yılmaz" --email=ayse@ornek.com --role=admin --password-file=- < sifre.txt
 * Şifre komut satırına YAZILMAZ (süreç listesinde görünür); dosyadan ya da standart girdiden (-) okunur.
 */
#[Signature('user:create
    {--name= : Ad soyad}
    {--email= : E-posta}
    {--role= : admin ya da customer}
    {--password-file= : Şifrenin okunacağı dosya ("-" = standart girdi)}')]
#[Description('Yeni bir yönetici veya müşteri hesabı oluşturur')]
class CreateUser extends Command
{
    public function handle(): int
    {
        // Laravel Prompts: etkileşimli, doğrulamalı komut satırı soruları (seçenek verilmediyse)
        $name = $this->option('name') ?: text('Ad soyad', required: true);
        $email = $this->option('email') ?: text('E-posta', required: true, validate: fn (string $value) => $this->emailError($value));
        $password = $this->option('password-file') !== null
            ? $this->readPassword($this->option('password-file'))
            : password('Şifre (en az 8 karakter)', required: true, validate: fn (string $value) => $this->passwordError($value));
        $role = $this->option('role') ?: select('Rol', ['admin' => 'Yönetici', 'customer' => 'Müşteri'], default: 'admin');

        // Seçeneklerle gelen değerler sorulardaki doğrulamadan geçmedi: aynı kurallar burada
        if ($error = $this->emailError($email) ?? $this->passwordError($password)
            ?? (Role::tryFrom($role) ? null : 'Rol admin ya da customer olmalı.')) {
            $this->error($error);

            return self::FAILURE;
        }

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

    private function emailError(string $email): ?string
    {
        return Validator::make(
            ['email' => $email],
            ['email' => ['required', 'email', 'unique:users,email']],
        )->errors()->first('email') ?: null;
    }

    private function passwordError(string $password): ?string
    {
        return strlen($password) < 8 ? 'Şifre en az 8 karakter olmalı.' : null;
    }

    private function readPassword(string $file): string
    {
        $content = $file === '-' ? stream_get_contents(fopen('php://stdin', 'r')) : @file_get_contents($file);

        // Sondaki satır sonu şifreye dahil değil (echo/heredoc ekler)
        return rtrim((string) $content, "\r\n");
    }
}
