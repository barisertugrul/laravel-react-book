<?php

namespace App\Http\Controllers;

use App\Actions\UndoLastStatusChange;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class UndoTicketStatusController extends Controller
{
    public function __invoke(Request $request, Ticket $ticket, UndoLastStatusChange $undo): RedirectResponse
    {
        Gate::authorize('undo', $ticket);

        $undo->handle($ticket, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Geri alındı. Durum: '.$ticket->status->label()]);

        return back();
    }
}
