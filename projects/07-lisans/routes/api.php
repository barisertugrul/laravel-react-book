<?php

use App\Http\Controllers\Api\LicenseApiController;
use Illuminate\Support\Facades\Route;

// Lisans API'si: /api/v1/...  (routes/api.php'deki adreslerin önüne Laravel "api/" ekler)
// signed-response: her yanıt Ed25519 ile imzalanır (bkz. SignLicenseResponse)
Route::prefix('v1/licenses')->name('api.licenses.')->middleware('signed-response')->group(function () {
    Route::post('activate', [LicenseApiController::class, 'activate'])
        ->middleware('throttle:license-activate')
        ->name('activate');

    Route::middleware(['auth:sanctum', 'throttle:license-api'])->group(function () {
        Route::get('check', [LicenseApiController::class, 'check'])->name('check');
        Route::post('deactivate', [LicenseApiController::class, 'deactivate'])->name('deactivate');
    });
});
