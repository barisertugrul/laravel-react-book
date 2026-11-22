<?php

use Illuminate\Support\Facades\Schedule;

// Ödeme süresi dolan siparişlerin stoğunu geri bırak.
// Zamanlayıcının çalışması için: php artisan schedule:work (geliştirme)
// veya sunucuda her dakika "php artisan schedule:run" (Bölüm 10).
Schedule::command('orders:expire')->everyMinute()->withoutOverlapping();
