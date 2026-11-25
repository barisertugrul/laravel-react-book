<?php

namespace App\Console\Commands;

use App\Jobs\CheckSiteHealth;
use App\Models\Site;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Zamanlayıcı her 15 dakikada bir çalıştırır (routes/console.php) */
#[Signature('sites:check')]
#[Description('Bütün ajansların sitelerini sağlık kontrolü için kuyruğa koyar')]
class CheckSites extends Command
{
    public function handle(): int
    {
        $count = 0;

        // Komut satırında ajans bağlamı yok: tüm kiracıları gezdiğimizi AÇIKÇA söylüyoruz.
        // Son 10 dakikada ("Şimdi kontrol et" ile) kontrol edilmiş siteler bu turu atlar.
        Site::acrossAgencies()
            ->where(fn ($query) => $query->whereNull('last_checked_at')->orWhere('last_checked_at', '<', now()->subMinutes(10)))
            ->lazyById()
            ->each(function (Site $site) use (&$count): void {
                CheckSiteHealth::dispatch($site);
                $count++;
            });

        $this->info("{$count} site kuyruğa kondu.");

        return self::SUCCESS;
    }
}
