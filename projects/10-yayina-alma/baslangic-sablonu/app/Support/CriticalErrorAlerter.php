<?php

namespace App\Support;

use App\Notifications\CriticalErrorOccurred;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class CriticalErrorAlerter
{
    public function handle(Throwable $e): void
    {
        $to = config('monitoring.alert_email');

        if (blank($to)) {
            return;
        }

        // Aynı hata her istekte tekrarlanır: 10 dakikada yüzlerce e-posta yerine bir tane
        $fingerprint = sha1(get_class($e).'|'.$e->getFile().'|'.$e->getLine());
        $minutes = config('monitoring.alert_throttle_minutes');

        if (! Cache::add("critical-alert:{$fingerprint}", true, now()->addMinutes($minutes))) {
            return;
        }

        // Birbirinden farklı hatalar art arda gelirse (ör. veritabanı düştü) toplam da sınırlı
        if (! RateLimiter::attempt('critical-alert:hourly', config('monitoring.alert_hourly_cap'), fn () => true, 3600)) {
            return;
        }

        try {
            // Senkron gönderilir: hatanın nedeni kuyruğun kendisi olabilir
            Notification::route('mail', $to)->notifyNow(CriticalErrorOccurred::fromThrowable($e));
        } catch (Throwable $mailError) {
            // Bildirim gönderilemedi diye yeni bir hata zinciri başlatılmaz; durum yalnızca loga yazılır
            Log::warning('Kritik hata e-postası gönderilemedi: '.$mailError->getMessage());
        }
    }
}
