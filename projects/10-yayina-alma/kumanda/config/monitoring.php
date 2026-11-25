<?php

return [

    /*
     * Kritik hata e-postası. Adres boşsa e-posta gönderilmez (geliştirme ve testlerde varsayılan).
     * Hataların hepsi yine loga yazılır; burada sınırlanan yalnızca e-postadır.
     */
    'alert_email' => env('ALERT_EMAIL'),

    // Aynı hata (sınıf + dosya + satır) bu süre içinde yalnızca bir kez e-posta olur
    'alert_throttle_minutes' => (int) env('ALERT_THROTTLE_MINUTES', 10),

    // Farklı hatalar art arda gelse bile saatte en çok bu kadar e-posta
    'alert_hourly_cap' => (int) env('ALERT_HOURLY_CAP', 20),

];
