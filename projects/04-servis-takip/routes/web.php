<?php

use App\Http\Controllers\AssignTicketController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketReceiptController;
use App\Http\Controllers\TicketStatusController;
use App\Http\Controllers\TrackingController;
use App\Http\Controllers\UndoTicketStatusController;
use Illuminate\Support\Facades\Route;

// --- Müşteri: servis takibi (giriş gerekmez) -----------------------------------
// Ana sayfa müşteri sorgulama sayfasına yönlenir. Aynı controller metodunu iki adrese
// bağlamıyoruz: Wayfinder her metot için tek bir TypeScript fonksiyonu üretebilsin.
Route::redirect('/', '/takip')->name('home');
Route::get('takip', [TrackingController::class, 'lookup'])->name('tracking.lookup');
Route::post('takip', [TrackingController::class, 'find'])
    ->middleware('throttle:10,1') // tahmin denemelerine karşı
    ->name('tracking.find');
// {ticket:tracking_code}: bu adreste kayıt, tahmin edilemez takip koduyla bulunur
Route::get('takip/{ticket:tracking_code}', [TrackingController::class, 'show'])->name('tracking.show');

// --- Servis paneli ---------------------------------------------------------------
Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('dashboard', '/tickets')->name('dashboard');

    Route::resource('tickets', TicketController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('tickets/{ticket}/status', TicketStatusController::class)->name('tickets.status');
    Route::post('tickets/{ticket}/undo', UndoTicketStatusController::class)->name('tickets.undo');
    Route::patch('tickets/{ticket}/assign', AssignTicketController::class)->name('tickets.assign');
    Route::get('tickets/{ticket}/receipt', TicketReceiptController::class)->name('tickets.receipt');

    // Yalnızca yöneticiler
    Route::middleware('role:admin')->group(function () {
        Route::get('team', [TeamController::class, 'index'])->name('team.index');
        Route::post('team', [TeamController::class, 'store'])->name('team.store');
        Route::patch('team/{user}', [TeamController::class, 'update'])->name('team.update');
    });
});

require __DIR__.'/settings.php';
