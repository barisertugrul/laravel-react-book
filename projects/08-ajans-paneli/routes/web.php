<?php

use App\Http\Controllers\AgencyController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\SiteCheckController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Ajans gerektirmeyen sayfalar: yeni ajans, ajans değiştirme, davet kabulü
    Route::get('ajanslar/yeni', [AgencyController::class, 'create'])->name('agencies.create');
    Route::post('ajanslar', [AgencyController::class, 'store'])->name('agencies.store');
    Route::post('ajanslar/{agency}/sec', [AgencyController::class, 'select'])->name('agencies.select');

    Route::middleware('signed')->group(function () {
        Route::get('davet/{invitation}', [InvitationController::class, 'show'])->name('invitations.show');
        Route::post('davet/{invitation}', [InvitationController::class, 'accept'])->name('invitations.accept');
    });

    // Etkin bir ajans gerektiren her şey
    Route::middleware('agency')->group(function () {
        Route::get('dashboard', DashboardController::class)->name('dashboard');

        Route::get('siteler/yeni', [SiteController::class, 'create'])->name('sites.create');
        Route::post('siteler', [SiteController::class, 'store'])->name('sites.store');
        Route::get('siteler/{site}', [SiteController::class, 'show'])->name('sites.show');
        Route::post('siteler/{site}/kontrol', SiteCheckController::class)->name('sites.check');

        Route::get('ajans', [AgencyController::class, 'edit'])->name('agency.edit');

        // Yalnızca ajans sahibi
        Route::middleware('can:manage-agency')->group(function () {
            Route::delete('siteler/{site}', [SiteController::class, 'destroy'])->name('sites.destroy');
            Route::patch('ajans', [AgencyController::class, 'update'])->name('agency.update');
            Route::post('ajans/davetler', [InvitationController::class, 'store'])->name('agency.invitations.store');
            Route::delete('ajans/davetler/{invitation}', [InvitationController::class, 'destroy'])->name('agency.invitations.destroy');
            Route::delete('ajans/uyeler/{user}', [MemberController::class, 'destroy'])->name('agency.members.destroy');
        });
    });
});

require __DIR__.'/settings.php';
