<?php

namespace Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Yedekleme ayarlarının bozulmadığını denetler. Asıl yedek alma sunucuda denenir
 * (mysqldump gerekir): php artisan backup:run
 */
class BackupConfigTest extends TestCase
{
    public function test_backup_commands_are_scheduled(): void
    {
        $commands = collect(app(Schedule::class)->events())
            ->map(fn ($event) => Str::after($event->command, 'artisan'))
            ->map(fn ($command) => trim($command, " '\""));

        $this->assertTrue($commands->contains(fn ($c) => str_starts_with($c, 'backup:clean')));
        $this->assertTrue($commands->contains(fn ($c) => str_starts_with($c, 'backup:run')));
        $this->assertTrue($commands->contains(fn ($c) => str_starts_with($c, 'backup:monitor')));
    }

    public function test_backups_are_not_written_inside_the_backed_up_folders(): void
    {
        $includes = config('backup.backup.source.files.include');

        foreach (config('backup.backup.destination.disks') as $disk) {
            $root = config("filesystems.disks.{$disk}.root");
            if ($root === null) {
                continue; // s3 gibi uzak diskler
            }

            foreach ($includes as $include) {
                $this->assertFalse(
                    str_starts_with($this->normalize($root), $this->normalize($include).'/'),
                    "Yedek diski ({$root}) yedeklenen klasörün ({$include}) içinde: her yedek öncekini de içerir.",
                );
            }
        }
    }

    public function test_database_is_backed_up_but_code_is_not(): void
    {
        $this->assertSame([config('database.default')], config('backup.backup.source.databases'));
        $this->assertNotContains(base_path(), config('backup.backup.source.files.include'));
    }

    public function test_empty_env_values_fall_back_to_safe_defaults(): void
    {
        // .env.example'daki "BACKUP_NOTIFY_EMAIL=" boş dize döndürür; env()'in varsayılanı yalnızca
        // değişken HİÇ yoksa devreye girer. spatie/laravel-backup açılışta adresi doğrular:
        // boş adres her artisan komutunu düşürüyordu (Bölüm 10, CI'da bulundu).
        $keys = ['BACKUP_NOTIFY_EMAIL', 'BACKUP_DISKS', 'ALERT_EMAIL'];
        $old = array_map(fn ($k) => $_ENV[$k] ?? null, array_combine($keys, $keys));

        try {
            foreach ($keys as $key) {
                $_ENV[$key] = '';
                $_SERVER[$key] = '';
            }
            $config = require config_path('backup.php');
        } finally {
            foreach ($old as $key => $value) {
                if ($value === null) {
                    unset($_ENV[$key], $_SERVER[$key]);
                } else {
                    $_ENV[$key] = $_SERVER[$key] = $value;
                }
            }
        }

        $this->assertNotFalse(filter_var($config['notifications']['mail']['to'], FILTER_VALIDATE_EMAIL));
        $this->assertSame(['backups'], $config['backup']['destination']['disks']);
        $this->assertSame(['backups'], $config['monitor_backups'][0]['disks']);
    }

    private function normalize(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }
}
