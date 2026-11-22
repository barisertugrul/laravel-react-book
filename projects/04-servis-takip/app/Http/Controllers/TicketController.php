<?php

namespace App\Http\Controllers;

use App\Actions\ChangeTicketStatus;
use App\Enums\Role;
use App\Enums\TicketStatus;
use App\Http\Requests\StoreTicketRequest;
use App\Models\Customer;
use App\Models\Ticket;
use App\Models\User;
use App\Policies\TicketPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class TicketController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $request->only(['status', 'q', 'mine']);

        $tickets = Ticket::query()
            ->visibleTo($user)
            ->with(['customer:id,name,phone', 'technician:id,name'])
            // when(): koşul doluysa sorguya ekle; filtreler birbirinden bağımsız
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['mine'] ?? null, fn ($q) => $q->where('assigned_to', $user->id))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('code', 'like', "%{$term}%")
                ->orWhere('brand', 'like', "%{$term}%")
                ->orWhere('model', 'like', "%{$term}%")
                ->orWhereHas('customer', fn ($q) => $q
                    ->where('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%"))))
            ->latest('id')
            ->paginate(10)
            ->withQueryString(); // sayfa bağlantıları filtreleri korusun

        // Her durumda kaç kayıt var? Tek sorgu: GROUP BY status
        $counts = Ticket::query()->visibleTo($user)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return Inertia::render('tickets/index', [
            'tickets' => $tickets,
            'filters' => $filters,
            'counts' => $counts,
            'statuses' => TicketStatus::options(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('tickets/create');
    }

    public function store(StoreTicketRequest $request): RedirectResponse
    {
        $ticket = DB::transaction(function () use ($request) {
            // Aynı telefonla daha önce gelen müşteri varsa onu kullan, yoksa oluştur
            $customer = Customer::updateOrCreate(
                ['phone' => Customer::normalizePhone($request->customer_phone)],
                array_filter([
                    'name' => $request->customer_name,
                    'email' => $request->customer_email,
                ]),
            );

            $ticket = $customer->tickets()->create($request->ticketData());

            $ticket->events()->create([
                'user_id' => $request->user()->id,
                'to_status' => TicketStatus::Received,
                'note' => 'Cihaz teslim alındı.',
            ]);

            return $ticket;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$ticket->code} oluşturuldu."]);

        return to_route('tickets.show', $ticket);
    }

    public function show(Request $request, Ticket $ticket): Response
    {
        Gate::authorize('view', $ticket);

        $user = $request->user();

        return Inertia::render('tickets/show', [
            'ticket' => $ticket->load(['customer', 'technician:id,name', 'events.user:id,name']),
            'transitions' => array_map(fn (TicketStatus $s) => [
                'value' => $s->value,
                'label' => $s->label(),
                'requires_note' => $ticket->status->requiresReason($s),
                'confirm' => $s->needsConfirmation(),
            ], $ticket->status->transitions()),
            'can' => [
                'update' => $user->can('update', $ticket) || $user->can('claim', $ticket),
                'assign' => $user->can('assign', $ticket),
                'undo' => $user->can('undo', $ticket),
            ],
            'undoWindowMinutes' => TicketPolicy::UNDO_WINDOW_MINUTES,
            'notifyDelayMinutes' => ChangeTicketStatus::NOTIFY_DELAY_MINUTES,
            'technicians' => $user->can('assign', $ticket)
                ? User::where('role', Role::Technician)->orderBy('name')->get(['id', 'name'])
                : [],
        ]);
    }
}
