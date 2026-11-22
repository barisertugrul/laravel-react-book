<?php

return [
    // Kuruş cinsinden: 59,90 ₺ kargo; 750 ₺ ve üzeri siparişlerde ücretsiz
    'shipping_fee' => 5990,
    'free_shipping_from' => 75000,

    // fake: hesap gerektirmeyen, geliştirme ve testler için sahte sağlayıcı
    // iyzico: iyzico Checkout Form (önce sandbox ile deneyin)
    'payment_provider' => env('PAYMENT_PROVIDER', 'fake'),

    'fake' => [
        // Sahte sağlayıcının webhook'ları imzaladığı gizli anahtar
        'secret' => env('FAKE_PAYMENT_SECRET', env('APP_KEY')),
    ],

    'iyzico' => [
        'api_key' => env('IYZICO_API_KEY'),
        'secret_key' => env('IYZICO_SECRET_KEY'),
        'base_url' => env('IYZICO_BASE_URL', 'https://sandbox-api.iyzipay.com'),
    ],
];
