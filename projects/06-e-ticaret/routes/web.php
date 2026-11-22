<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\Payments\FakeProviderController;
use App\Http\Controllers\Payments\FakeWebhookController;
use App\Http\Controllers\Payments\IyzicoCallbackController;
use App\Http\Controllers\ShopController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// --- Vitrin: herkese açık ---
Route::get('/', [ShopController::class, 'index'])->name('home');
Route::get('urun/{product}', [ShopController::class, 'show'])->name('products.show');

// Sepet oturumda tutulur; giriş yapmadan da kullanılabilir.
// Ürün modelinin adres anahtarı slug; sepet işlemlerinde id kullanıyoruz: {product:id}
Route::get('sepet', [CartController::class, 'index'])->name('cart.index');
Route::post('sepet', [CartController::class, 'store'])->name('cart.store');
Route::patch('sepet/{product:id}', [CartController::class, 'update'])->name('cart.update');
Route::delete('sepet/{product:id}', [CartController::class, 'destroy'])->name('cart.destroy');

Route::middleware(['auth', 'verified'])->group(function () {
    // Fortify girişten sonra buraya yönlendirir; role göre doğru panele gönderiyoruz
    Route::get('dashboard', fn (Request $request) => $request->user()->isAdmin()
        ? to_route('admin.orders.index')
        : to_route('orders.index'))->name('dashboard');

    Route::get('odeme', [CheckoutController::class, 'create'])->name('checkout.create');
    Route::post('odeme', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::get('odeme/sonuc/{order}', [CheckoutController::class, 'result'])->name('checkout.result');

    Route::get('siparislerim', [OrderController::class, 'index'])->name('orders.index');
    Route::get('siparislerim/{order}', [OrderController::class, 'show'])->name('orders.show');

    // --- Yönetim paneli ---
    Route::prefix('admin')->name('admin.')->middleware('role:admin')->group(function () {
        Route::resource('products', Admin\ProductController::class)->except(['show', 'destroy']);
        Route::get('orders', [Admin\OrderController::class, 'index'])->name('orders.index');
        Route::get('orders/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');
        Route::patch('orders/{order}/ship', [Admin\OrderController::class, 'ship'])->name('orders.ship');
    });
});

// --- Ödeme sağlayıcıları ---
// iyzico, müşterinin tarayıcısını bu adrese POST eder (CSRF dışı; bkz. bootstrap/app.php)
Route::post('odeme/iyzico/donus', IyzicoCallbackController::class)->name('iyzico.callback');

// Sahte sağlayıcı: yalnızca seçiliyse tanımlı. İmzalı adres, sipariş numarasıyla oynanmasını engeller.
if (config('shop.payment_provider') === 'fake') {
    Route::middleware('signed')->group(function () {
        Route::get('sahte-odeme/{order}', [FakeProviderController::class, 'show'])->name('fake-provider.show');
        Route::post('sahte-odeme/{order}', [FakeProviderController::class, 'store'])->name('fake-provider.store');
    });
    Route::post('webhooks/fake', FakeWebhookController::class)->name('webhooks.fake');
}

require __DIR__.'/settings.php';
