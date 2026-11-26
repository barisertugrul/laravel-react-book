<?php

use Illuminate\Support\Facades\Route;

// Personel paneli: tanıtım sayfası yok. Başlangıç kitinin İngilizce örnek sayfası (welcome) silindi;
// kök adres panele gider, giriş yapmamış ziyaretçiyi auth katmanı giriş sayfasına yönlendirir (Bölüm 10).
Route::redirect('/', '/dashboard')->name('home');

// Yayındaki sürüm (build.sh paketin köküne VERSION yazar). Dağıtım betiği, yeni sürüme geçtikten sonra
// PHP'nin gerçekten yeni kodu çalıştırdığını buradan anlar (paylaşımlı hostingte eski sürüm bir süre kalabilir).
Route::get('surum', fn () => response(is_file(base_path('VERSION')) ? trim(file_get_contents(base_path('VERSION'))) : 'yerel', 200, [
    'Content-Type' => 'text/plain; charset=utf-8',
    'Cache-Control' => 'no-store',
]))->name('version');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
