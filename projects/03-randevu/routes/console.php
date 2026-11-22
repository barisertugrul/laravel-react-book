<?php

use Illuminate\Support\Facades\Schedule;

// Her akşam 18:00'de yarınki randevular için hatırlatma gönder.
// Sunucuda çalışması için cron'a tek satır eklenir (bkz. Bölüm 10):
//   * * * * * cd /proje && php artisan schedule:run >> /dev/null 2>&1
Schedule::command('appointments:send-reminders')->dailyAt('18:00');
