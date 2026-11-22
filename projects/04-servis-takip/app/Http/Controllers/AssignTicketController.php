<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class AssignTicketController extends Controller
{
    public function __invoke(Request $request, Ticket $ticket): RedirectResponse
    {
        Gate::authorize('assign', $ticket);

        $validated = $request->validate([
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('role', Role::Technician->value)],
        ]);

        $ticket->update($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Teknisyen ataması güncellendi.']);

        return back();
    }
}
