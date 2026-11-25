<?php

use App\Http\Controllers\AgencyController;
use App\Http\Controllers\Billing\BillingController;
use App\Http\Controllers\Billing\FakeBillingProviderController;
use App\Http\Controllers\Billing\FakeBillingWebhookController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\InvitationController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\OperationController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\PackageDownloadController;
use App\Http\Controllers\SiteCheckController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

// Yayındaki sürüm (build.sh paketin köküne VERSION yazar). Dağıtım betiği, symlink değiştikten sonra
// PHP'nin yeni sürüme geçtiğini buradan anlar: paylaşımlı hostingte realpath önbelleği eskiyi bir süre tutar.
Route::get('surum', fn () => response(is_file(base_path('VERSION')) ? trim(file_get_contents(base_path('VERSION'))) : 'yerel', 200, [
    'Content-Type' => 'text/plain; charset=utf-8',
    'Cache-Control' => 'no-store',
]))->name('version');

// Sahte abonelik sağlayıcısı (gerçekte sağlayıcının sitesi) ve onun bildirimi.
// fake-billing: canlıda (izin verilmedikçe) bu adresler hiç yokmuş gibi 404 döner.
Route::middleware('fake-billing')->group(function () {
    Route::middleware('signed')->group(function () {
        Route::get('sahte-odeme/abonelik', [FakeBillingProviderController::class, 'show'])->name('fake-billing.show');
        Route::post('sahte-odeme/abonelik', [FakeBillingProviderController::class, 'store'])->name('fake-billing.store');
    });
    Route::post('webhooks/abonelik', FakeBillingWebhookController::class)->name('webhooks.billing');
});

// WordPress sitesinin paket indirdiği adres: oturum yok, yetki imzalı adresten (bkz. PackageDownloadController)
Route::get('paketler/{package}/indir/{site}', PackageDownloadController::class)
    ->whereNumber(['package', 'site'])
    ->middleware(['signed', 'throttle:30,1'])
    ->name('packages.download');

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
        Route::put('siteler/{site}/baglanti', [SiteController::class, 'repair'])->name('sites.repair');

        Route::get('islemler', [OperationController::class, 'index'])->name('operations.index');
        Route::get('islemler/yeni', [OperationController::class, 'create'])->name('operations.create');
        Route::post('islemler', [OperationController::class, 'store'])->name('operations.store');
        Route::get('islemler/{operation}', [OperationController::class, 'show'])->name('operations.show');
        Route::post('islemler/{operation}/iptal', [OperationController::class, 'cancel'])->name('operations.cancel');

        Route::get('paketler', [PackageController::class, 'index'])->name('packages.index');

        Route::get('ajans', [AgencyController::class, 'edit'])->name('agency.edit');
        Route::get('abonelik', [BillingController::class, 'index'])->name('billing.index');

        // Yalnızca ajans sahibi
        Route::middleware('can:manage-agency')->group(function () {
            Route::delete('siteler/{site}', [SiteController::class, 'destroy'])->name('sites.destroy');
            Route::post('paketler', [PackageController::class, 'store'])->name('packages.store');
            Route::post('abonelik/plan/{plan}', [BillingController::class, 'subscribe'])->whereIn('plan', array_keys(config('billing.plans')))->name('billing.subscribe');
            Route::post('abonelik/iptal', [BillingController::class, 'cancel'])->name('billing.cancel');
            Route::post('abonelik/kart', [BillingController::class, 'card'])->name('billing.card');
            Route::delete('paketler/{package}', [PackageController::class, 'destroy'])->name('packages.destroy');
            Route::patch('ajans', [AgencyController::class, 'update'])->name('agency.update');
            Route::post('ajans/davetler', [InvitationController::class, 'store'])->name('agency.invitations.store');
            Route::delete('ajans/davetler/{invitation}', [InvitationController::class, 'destroy'])->name('agency.invitations.destroy');
            Route::delete('ajans/uyeler/{user}', [MemberController::class, 'destroy'])->name('agency.members.destroy');
        });
    });
});

require __DIR__.'/settings.php';
