<?php

use Illuminate\Support\Facades\Schedule;

// Sağlık kontrolleri. Geliştirmede "composer run dev" zamanlayıcıyı da başlatır (AppServiceProvider);
// sunucuda her dakika "php artisan schedule:run" çalışır (Bölüm 10).
Schedule::command('sites:check')->everyFifteenMinutes()->withoutOverlapping();

// Abonelik yenileme ve ek süre (Bölüm 9): saatte bir; para çeken iş kendisi tekilleşir (ShouldBeUnique)
Schedule::command('billing:renew')->hourly()->withoutOverlapping();
