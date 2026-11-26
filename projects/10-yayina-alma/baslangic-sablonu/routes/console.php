<?php

use Illuminate\Support\Facades\Schedule;

// Sunucuda her dakika "php artisan schedule:run" çalışır (cron; Bölüm 10, deploy/cron).

// Yedekleme: önce eski yedekleri temizle, sonra yenisini al; sabah da sağlığını denetle
// (son yedek 1 günden eskiyse ya da hiç yoksa BACKUP_NOTIFY_EMAIL adresine e-posta gelir)
Schedule::command('backup:clean')->dailyAt('01:00');
Schedule::command('backup:run')->dailyAt('01:30')->withoutOverlapping();
Schedule::command('backup:monitor')->dailyAt('09:00');
