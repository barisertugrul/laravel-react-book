<?php

use App\Http\Controllers\Admin\AppointmentStatusController;
use App\Http\Controllers\Admin\CalendarController;
use App\Http\Controllers\Admin\ServiceController;
use App\Http\Controllers\Admin\WorkingHoursController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\CancelAppointmentController;
use App\Models\Appointment;
use App\Notifications\AppointmentBooked;
use Illuminate\Support\Facades\Route;

// --- Müşteri tarafı (giriş gerekmez) ---------------------------------------
Route::get('/', [BookingController::class, 'show'])->name('home'); // kitin giriş sayfaları 'home' adını kullanıyor
Route::post('randevu', [BookingController::class, 'store'])
    ->middleware('throttle:10,1') // aynı IP'den dakikada en fazla 10 deneme
    ->name('booking.store');
Route::get('randevu/{appointment}/tamam', [BookingController::class, 'success'])
    ->middleware('signed')
    ->name('booking.success');
Route::get('randevu/{appointment}/iptal', [CancelAppointmentController::class, 'show'])
    ->middleware('signed')
    ->name('booking.cancel');
Route::post('randevu/{appointment}/iptal', [CancelAppointmentController::class, 'destroy'])
    ->middleware('signed');

// --- İşletme paneli -----------------------------------------------------------
Route::middleware(['auth', 'verified'])->group(function () {
    Route::redirect('dashboard', '/calendar')->name('dashboard');
    Route::get('calendar', CalendarController::class)->name('calendar');
    Route::patch('appointments/{appointment}/status', AppointmentStatusController::class)->name('appointments.status');
    Route::resource('services', ServiceController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::get('working-hours', [WorkingHoursController::class, 'edit'])->name('working-hours.edit');
    Route::put('working-hours', [WorkingHoursController::class, 'update'])->name('working-hours.update');
});

// --- Yalnızca geliştirme ortamında: e-posta önizleme --------------------------
if (app()->isLocal()) {
    Route::get('dev/mail/randevu', function () {
        $appointment = Appointment::with('service')->latest()->firstOrFail();

        return (new AppointmentBooked($appointment))->toMail($appointment);
    });
}

require __DIR__.'/settings.php';
