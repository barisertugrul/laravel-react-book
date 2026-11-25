<?php

use Illuminate\Support\Facades\Route;

// Personel paneli: tanıtım sayfası yok. Başlangıç kitinin İngilizce örnek sayfası (welcome) silindi;
// kök adres panele gider, giriş yapmamış ziyaretçiyi auth katmanı giriş sayfasına yönlendirir (Bölüm 10).
Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
