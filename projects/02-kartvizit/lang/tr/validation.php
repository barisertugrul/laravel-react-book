<?php

/*
| Laravel doğrulama mesajlarının Türkçesi (kitapta kullanılan kurallar).
| Eksik bir kural için Laravel İngilizce mesaja geri döner; ihtiyaç duydukça ekleyin.
| Tam liste: php artisan lang:publish ile gelen lang/en/validation.php
*/

return [
    'accepted' => ':attribute kabul edilmelidir.',
    'alpha_dash' => ':attribute yalnızca harf, rakam, tire ve alt çizgi içerebilir.',
    'array' => ':attribute bir liste olmalıdır.',
    'boolean' => ':attribute alanı doğru veya yanlış olmalıdır.',
    'confirmed' => ':attribute tekrarı eşleşmiyor.',
    'current_password' => 'Şifre hatalı.',
    'date' => ':attribute geçerli bir tarih olmalıdır.',
    'email' => ':attribute geçerli bir e-posta adresi olmalıdır.',
    'enum' => 'Seçilen :attribute geçersiz.',
    'exists' => 'Seçilen :attribute bulunamadı.',
    'file' => ':attribute bir dosya olmalıdır.',
    'image' => ':attribute bir görsel olmalıdır.',
    'in' => 'Seçilen :attribute geçersiz.',
    'integer' => ':attribute bir tam sayı olmalıdır.',
    'lowercase' => ':attribute küçük harflerden oluşmalıdır.',
    'max' => [
        'array' => ':attribute en fazla :max öğe içerebilir.',
        'file' => ':attribute en fazla :max kilobayt olabilir.',
        'numeric' => ':attribute en fazla :max olabilir.',
        'string' => ':attribute en fazla :max karakter olabilir.',
    ],
    'mimes' => ':attribute şu türlerden biri olmalıdır: :values.',
    'min' => [
        'array' => ':attribute en az :min öğe içermelidir.',
        'file' => ':attribute en az :min kilobayt olmalıdır.',
        'numeric' => ':attribute en az :min olmalıdır.',
        'string' => ':attribute en az :min karakter olmalıdır.',
    ],
    'not_in' => 'Seçilen :attribute geçersiz.',
    'numeric' => ':attribute bir sayı olmalıdır.',
    'regex' => ':attribute biçimi geçersiz.',
    'required' => ':attribute alanı zorunludur.',
    'string' => ':attribute bir metin olmalıdır.',
    'unique' => 'Bu :attribute zaten kullanılıyor.',
    'url' => ':attribute geçerli bir adres olmalıdır (https:// ile başlayın).',

    // Alan adlarının mesajlarda görünecek Türkçe karşılıkları
    'attributes' => [
        'name' => 'ad',
        'email' => 'e-posta',
        'password' => 'şifre',
        'full_name' => 'ad soyad',
        'slug' => 'kart adresi',
        'title' => 'unvan',
        'company' => 'şirket',
        'bio' => 'hakkında',
        'phone' => 'telefon',
        'website' => 'web sitesi',
        'address' => 'adres',
        'theme' => 'tema',
        'avatar' => 'fotoğraf',
        'links' => 'bağlantılar',
        'plan' => 'plan',
    ],
];
