<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Müşterinin, giriş yapmadan cihazının durumunu izlediği sayfalar. */
class TrackingController extends Controller
{
    public function lookup(): Response
    {
        return Inertia::render('tracking/lookup', ['business' => config('app.name')]);
    }

    /** Fişteki servis numarası + telefonun son 4 hanesi ile arama. */
    public function find(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'phone_last4' => ['required', 'digits:4'],
        ]);

        $ticket = Ticket::with('customer')->where('code', mb_strtoupper(trim($validated['code'])))->first();

        // Numara doğru ama telefon yanlışsa da aynı mesajı veriyoruz: hangisinin yanlış olduğunu söylemek
        // kötü niyetli birine servis numaralarını tek tek deneme imkânı verir.
        if (! $ticket || ! str_ends_with(Customer::normalizePhone($ticket->customer->phone), $validated['phone_last4'])) {
            return back()->withErrors(['code' => 'Bu bilgilerle eşleşen bir servis kaydı bulunamadı.']);
        }

        return redirect($ticket->tracking_url);
    }

    /**
     * Müşteriye gösterilecek olaylar. Geri alınan bir değişiklik ve onu geri alan olay
     * birlikte gizlenir: müşteri için o değişiklik hiç olmamıştır (e-postası da gitmemiştir).
     */
    private function publicEvents(Ticket $ticket)
    {
        $events = $ticket->events()->where('is_public', true)->get();
        $reverted = $events->pluck('reverts_event_id')->filter();

        return $events->reject(fn ($e) => $e->isRevert() || $reverted->contains($e->id))->values();
    }

    public function show(Ticket $ticket): Response
    {
        return Inertia::render('tracking/show', [
            'business' => config('app.name'),
            // Yalnızca müşteriye gösterilecek alanlar: iç notlar ve teknisyen bilgisi gönderilmez
            'ticket' => [
                'code' => $ticket->code,
                'device' => trim("{$ticket->device_type} · {$ticket->brand} {$ticket->model}"),
                'status' => $ticket->status->value,
                'status_label' => $ticket->status->label(),
                'estimated_cost' => $ticket->estimated_cost,
                'final_cost' => $ticket->final_cost,
                'created_at' => $ticket->created_at,
            ],
            'events' => $this->publicEvents($ticket)->map(fn ($e) => [
                'id' => $e->id,
                'label' => $e->to_status->label(),
                'status' => $e->to_status->value,
                'note' => $e->note,
                'created_at' => $e->created_at,
            ]),
        ]);
    }
}
