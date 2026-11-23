<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\LicenseController;
use App\Http\Controllers\Payments\FakeProviderController;
use App\Http\Controllers\Payments\FakeWebhookController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\StoreController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// --- Vitrin: herkese açık ---
Route::get('/', [StoreController::class, 'index'])->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Fortify girişten sonra buraya yönlendirir; role göre doğru sayfaya gönderiyoruz
    Route::get('dashboard', fn (Request $request) => $request->user()->isAdmin()
        ? to_route('admin.licenses.index')
        : to_route('licenses.index'))->name('dashboard');

    Route::post('satin-al/{plan}', [PurchaseController::class, 'store'])->name('purchase.store');
    Route::get('satin-al/sonuc/{order}', [PurchaseController::class, 'result'])->name('purchase.result');

    // --- Müşteri portalı ---
    Route::get('lisanslarim', [LicenseController::class, 'index'])->name('licenses.index');
    Route::get('lisanslarim/{license}', [LicenseController::class, 'show'])->name('licenses.show');
    Route::delete('lisanslarim/{license}/siteler/{activation}', [LicenseController::class, 'destroyActivation'])->name('licenses.activations.destroy');

    // --- Yönetim paneli ---
    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
        Route::get('licenses', [Admin\LicenseController::class, 'index'])->name('licenses.index');
        Route::get('licenses/{license}', [Admin\LicenseController::class, 'show'])->name('licenses.show');
        Route::post('licenses/{license}/revoke', [Admin\LicenseController::class, 'revoke'])->name('licenses.revoke');
        Route::post('licenses/{license}/extend', [Admin\LicenseController::class, 'extend'])->name('licenses.extend');
    });
});

// --- Sahte ödeme sağlayıcısı (Bölüm 6): yalnızca seçiliyse tanımlı ---
if (config('licensing.payment_provider') === 'fake') {
    Route::middleware('signed')->group(function () {
        Route::get('sahte-odeme/{order}', [FakeProviderController::class, 'show'])->name('fake-provider.show');
        Route::post('sahte-odeme/{order}', [FakeProviderController::class, 'store'])->name('fake-provider.store');
    });
    Route::post('webhooks/fake', FakeWebhookController::class)->name('webhooks.fake');
}

require __DIR__.'/settings.php';
