<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;
use App\Notifications\AppointmentCancelledByCustomer;
use Illuminate\Support\Facades\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * E-postadaki imzalı bağlantıyla randevu iptali.
 * "signed" middleware, bağlantı değiştirilmişse veya süresi dolmuşsa 403 döndürür.
 */
class CancelAppointmentController extends Controller
{
    public function show(Request $request, Appointment $appointment): Response
    {
        return Inertia::render('booking/cancel', [
            'appointment' => $appointment->load('service'),
            // Form, imzayı içeren aynı adrese gönderilir
            'action' => $request->fullUrl(),
        ]);
    }

    public function destroy(Appointment $appointment): RedirectResponse
    {
        if ($appointment->status === AppointmentStatus::Confirmed) {
            $appointment->update(['status' => AppointmentStatus::Cancelled]);

            // İlk sürümde işletme, müşterinin iptalini ancak takvime bakınca fark ediyordu.
            Notification::send(User::all(), new AppointmentCancelledByCustomer($appointment));
        }

        return back();
    }
}
