<?php

use Illuminate\Support\Facades\Schedule;

// Sağlık kontrolleri. Geliştirmede "composer run dev" zamanlayıcıyı da başlatır (AppServiceProvider);
// sunucuda her dakika "php artisan schedule:run" çalışır (Bölüm 10).
Schedule::command('sites:check')->everyFifteenMinutes()->withoutOverlapping();

// Abonelik yenileme ve ek süre (Bölüm 9): saatte bir; para çeken iş kendisi tekilleşir (ShouldBeUnique)
Schedule::command('billing:renew')->hourly()->withoutOverlapping();

// Yedekleme (Bölüm 10): önce eski yedekleri temizle, sonra yenisini al; sabah da sağlığını denetle
// (son yedek 1 günden eskiyse ya da hiç yoksa e-posta gelir)
Schedule::command('backup:clean')->dailyAt('01:00');
Schedule::command('backup:run')->dailyAt('01:30')->withoutOverlapping();
Schedule::command('backup:monitor')->dailyAt('09:00');
