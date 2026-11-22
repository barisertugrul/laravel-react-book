<?php

namespace App\Http\Controllers;

use App\Actions\ChangeTicketStatus;
use App\Enums\TicketStatus;
use App\Http\Requests\ChangeStatusRequest;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class TicketStatusController extends Controller
{
    public function __invoke(ChangeStatusRequest $request, Ticket $ticket, ChangeTicketStatus $change): RedirectResponse
    {
        $change->handle(
            $ticket,
            TicketStatus::from($request->validated('status')),
            $request->user(),
            $request->validated('note'),
            $request->boolean('is_public', true),
            $request->validated('final_cost'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Durum: '.$ticket->status->label()]);

        return back();
    }
}
