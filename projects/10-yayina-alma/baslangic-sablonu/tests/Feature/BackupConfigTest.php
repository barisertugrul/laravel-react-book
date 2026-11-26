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

    private function normalize(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }
}
