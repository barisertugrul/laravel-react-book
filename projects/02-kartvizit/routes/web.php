<?php

use App\Http\Controllers\CardController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\PublicCardController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Herkese açık kart sayfası: /k/baris-ertugrul
Route::get('k/{card:slug}', [PublicCardController::class, 'show'])->name('cards.public');
Route::get('k/{card:slug}/vcard', [PublicCardController::class, 'vcard'])->name('cards.vcard');

Route::middleware(['auth', 'verified'])->group(function () {
    // Girişten sonra kullanıcı doğrudan kartlarına gider.
    Route::redirect('dashboard', '/cards')->name('dashboard');

    Route::resource('cards', CardController::class)->except(['show']);

    Route::get('plan', [PlanController::class, 'show'])->name('plan.show');
    Route::put('plan', [PlanController::class, 'update'])->name('plan.update');
});

require __DIR__.'/settings.php';
