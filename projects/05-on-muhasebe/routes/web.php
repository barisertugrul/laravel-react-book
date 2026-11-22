<?php

use App\Http\Controllers\CancelInvoiceController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\InvoicePaymentController;
use App\Http\Controllers\InvoicePdfController;
use App\Http\Controllers\ReportController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');

    Route::resource('contacts', ContactController::class)->except(['destroy']);

    // Faturalar düzenlenmez ve silinmez; yalnızca iptal edilir (bkz. "Güvenlik incelemesi")
    Route::resource('invoices', InvoiceController::class)->only(['index', 'create', 'store', 'show']);
    Route::post('invoices/{invoice}/payments', InvoicePaymentController::class)->name('invoices.payments.store');
    Route::post('invoices/{invoice}/cancel', CancelInvoiceController::class)->name('invoices.cancel');
    Route::get('invoices/{invoice}/pdf', InvoicePdfController::class)->name('invoices.pdf');

    Route::get('reports', ReportController::class)->name('reports');
    Route::get('export/invoices', ExportController::class)->name('export.invoices');
});

require __DIR__.'/settings.php';
