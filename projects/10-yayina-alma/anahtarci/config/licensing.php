<?php

return [
    // fake: hesap gerektirmeyen, geliştirme ve testler için sahte ödeme sağlayıcısı (Bölüm 6)
    'payment_provider' => env('PAYMENT_PROVIDER', 'fake'),

    'fake' => [
        // Sahte sağlayıcının webhook'ları imzaladığı gizli anahtar (HMAC)
        'secret' => env('FAKE_PAYMENT_SECRET', env('APP_KEY')),
    ],

    // Lisans anahtarlarını ve API yanıtlarını imzalayan Ed25519 GİZLİ anahtarı (base64).
    // Üretmek için: php artisan license:keygen
    // Açık anahtar bundan türetilir ve WordPress eklentisine gömülür; gizli anahtar sunucudan çıkmaz.
    'signing_key' => env('LICENSE_SIGNING_KEY'),

    // Satın alımla birlikte gelen güncelleme ve destek süresi (ay)
    'update_months' => 12,

    // Anahtarların başındaki ürün öneki ve biçim sürümü: ANH1.<yük>.<imza>
    'key_prefix' => 'ANH1',
];
