<?php

use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MenuController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\QrCodeController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Herkese açık menü: QR kod bu adrese yönlendirir.
Route::get('menu', MenuController::class)->name('menu');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::resource('categories', CategoryController::class)
        ->only(['index', 'store', 'update', 'destroy']);

    Route::resource('products', ProductController::class)->except(['show']);

    Route::get('qr-code', [QrCodeController::class, 'show'])->name('qr-code');
    Route::get('qr-code/download', [QrCodeController::class, 'download'])->name('qr-code.download');
});

require __DIR__.'/settings.php';
