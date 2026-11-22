<?php

namespace App\Http\Controllers\Admin;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Notifications\AppointmentCancelled;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AppointmentStatusController extends Controller
{
    public function __invoke(Request $request, Appointment $appointment): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(AppointmentStatus::class)],
        ]);

        $wasCancelled = $appointment->status === AppointmentStatus::Cancelled;
        $appointment->update($validated);

        // İlk sürümde işletme randevuyu iptal ettiğinde müşterinin haberi olmuyordu.
        if (! $wasCancelled && $appointment->status === AppointmentStatus::Cancelled && $appointment->starts_at->isFuture()) {
            $appointment->notify(new AppointmentCancelled($appointment));
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Randevu: '.$appointment->status->label()]);

        return back();
    }
}
