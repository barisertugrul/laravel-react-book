<?php

namespace App\Http\Controllers;

use App\Enums\AgencyRole;
use App\Models\Agency;
use App\Tenancy\CurrentAgency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgencyController extends Controller
{
    public function __construct(private CurrentAgency $current) {}

    public function create(): Response
    {
        return Inertia::render('agencies/create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:80']]);

        $agency = Agency::create($validated);
        $agency->addMember($request->user(), AgencyRole::Owner);
        $request->user()->switchAgency($agency);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$agency->name} oluşturuldu."]);

        return to_route('dashboard');
    }

    /** Ajans değiştirici. Üye olunmayan ajansın varlığını bile belli etmeyiz: 404 */
    public function select(Request $request, Agency $agency): RedirectResponse
    {
        abort_unless($request->user()->belongsToAgency($agency), 404);

        $request->user()->switchAgency($agency);

        return to_route('dashboard');
    }

    /** Ajans ayarları: ad, ekip, bekleyen davetler */
    public function edit(Request $request): Response
    {
        $agency = $this->current->get();

        return Inertia::render('agency/edit', [
            'agency' => $agency->only(['id', 'name']),
            'canManage' => $request->user()->can('manage-agency'),
            'members' => $agency->users()->orderBy('name')->get()->map(fn ($user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->pivot->role,
                'joined_at' => $user->pivot->created_at,
            ]),
            'invitations' => $agency->invitations()->whereNull('accepted_at')->where('expires_at', '>', now())
                ->with('inviter:id,name')->latest()->get()
                ->map(fn ($invitation) => [
                    'id' => $invitation->id,
                    'email' => $invitation->email,
                    'role' => $invitation->role->value,
                    'expires_at' => $invitation->expires_at,
                    'inviter' => $invitation->inviter?->name,
                ]),
            'roles' => array_map(fn (AgencyRole $role) => ['value' => $role->value, 'label' => $role->label()], AgencyRole::cases()),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->current->get()->update($request->validate(['name' => ['required', 'string', 'max:80']]));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ajans adı güncellendi.']);

        return back();
    }
}
