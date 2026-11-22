<?php

namespace App\Http\Controllers;

use App\Actions\BookAppointment;
use App\Http\Requests\BookingRequest;
use App\Models\Appointment;
use App\Models\Service;
use App\Support\Availability;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Müşterinin giriş yapmadan randevu aldığı herkese açık sayfa. */
class BookingController extends Controller
{
    public function show(Request $request, Availability $availability): Response
    {
        $service = Service::where('is_active', true)->find($request->integer('service'));
        $date = $request->date('date', 'Y-m-d');

        return Inertia::render('booking/show', [
            'business' => config('app.name'),
            'services' => Service::where('is_active', true)->orderBy('name')->get(),
            'days' => $availability->days(),
            'selected' => [
                'service' => $service?->id,
                'date' => $date?->toDateString(),
            ],
            // Saatler yalnızca hizmet ve tarih seçildiğinde hesaplanır.
            // Inertia::optional(): yalnızca istemci açıkça isterse (kısmi yenileme) çalışır.
            'slots' => Inertia::optional(fn () => $service && $date
                ? $availability->slots($service, CarbonImmutable::parse($date))
                : []),
        ]);
    }

    public function store(BookingRequest $request, BookAppointment $book): RedirectResponse
    {
        $appointment = $book->handle(
            Service::findOrFail($request->integer('service_id')),
            $request->startsAt(),
            $request->customer(),
        );

        // Başarı sayfası da imzalı: başkası id'yi tahmin ederek randevu bilgilerini göremez.
        return redirect()->signedRoute('booking.success', ['appointment' => $appointment->id]);
    }

    public function success(Appointment $appointment): Response
    {
        return Inertia::render('booking/success', [
            'appointment' => $appointment->load('service'),
            'cancelUrl' => $appointment->cancelUrl(),
        ]);
    }
}
