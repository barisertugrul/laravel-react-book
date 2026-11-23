<?php

return [
    // fake: hesap gerektirmeyen sahte sağlayıcı (Bölüm 6'daki gibi). Gerçek sağlayıcı için bkz. Bölüm 9 metni.
    'provider' => env('BILLING_PROVIDER', 'fake'),

    'fake' => [
        // Sahte sağlayıcının webhook'ları imzaladığı gizli anahtar (HMAC)
        'secret' => env('FAKE_BILLING_SECRET', env('APP_KEY')),
    ],

    // Ödeme alınamazsa ajansın ücretli planda kalacağı süre (gün); sonra ücretsiz plana düşer
    'grace_days' => 7,

    // Fiyatlar kuruş cinsinden, aylık. Kod plan ANAHTARINI bilir; ad ve fiyat buradan değişir.
    'plans' => [
        'baslangic' => ['name' => 'Başlangıç', 'price' => 0, 'max_sites' => 3],
        'ajans' => ['name' => 'Ajans', 'price' => 79900, 'max_sites' => 25],
        'kurumsal' => ['name' => 'Kurumsal', 'price' => 199900, 'max_sites' => 100],
    ],

    'free_plan' => 'baslangic',
];
