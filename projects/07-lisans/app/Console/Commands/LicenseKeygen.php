<?php

namespace App\Console\Commands;

use App\Support\Signer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Ed25519 anahtar çiftini üretir ve gizli anahtarı .env dosyasına yazar:
 *   php artisan license:keygen
 * Anahtar zaten varsa yalnızca açık anahtarı gösterir (WordPress eklentisine gömülecek olan).
 */
#[Signature('license:keygen {--force : Mevcut anahtarın üzerine yaz (eski anahtarların hepsi geçersiz olur!)}')]
#[Description('Lisans imza anahtarını üretir ve açık anahtarı gösterir')]
class LicenseKeygen extends Command
{
    public function handle(): int
    {
        $envPath = $this->laravel->environmentFilePath();
        $env = (string) file_get_contents($envPath);

        if (config('licensing.signing_key') && ! $this->option('force')) {
            $this->components->info('Lisans imza anahtarı zaten tanımlı.');
            $this->printPublicKey(Signer::publicKey());

            return self::SUCCESS;
        }

        [$secret, $public] = Signer::generateKeyPair();
        $line = 'LICENSE_SIGNING_KEY='.base64_encode($secret);

        // .env içinde satır varsa değiştir, yoksa sona ekle
        $env = preg_match('/^LICENSE_SIGNING_KEY=.*$/m', $env)
            ? preg_replace('/^LICENSE_SIGNING_KEY=.*$/m', $line, $env)
            : rtrim($env).PHP_EOL.$line.PHP_EOL;

        file_put_contents($envPath, $env);

        $this->components->info('Gizli anahtar .env dosyasına yazıldı. Bu anahtarı kimseyle paylaşmayın ve yedekleyin:');
        $this->components->warn('kaybederseniz yeni lisans üretebilirsiniz ama eskileri doğrulanamaz.');
        $this->printPublicKey($public);

        return self::SUCCESS;
    }

    private function printPublicKey(string $public): void
    {
        $this->newLine();
        $this->line('Açık anahtar (WordPress eklentisine gömülecek, gizli değildir):');
        $this->line('  <fg=green>'.base64_encode($public).'</>');
    }
}
