<?php

namespace App\Http\Controllers;

use App\Enums\AgencyRole;
use App\Models\AgencyInvitation;
use App\Notifications\AgencyInvitationSent;
use App\Tenancy\CurrentAgency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InvitationController extends Controller
{
    public const VALID_DAYS = 7;

    public function store(Request $request, CurrentAgency $current): RedirectResponse
    {
        $agency = $current->get();

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:120'],
            'role' => ['required', Rule::enum(AgencyRole::class)],
        ]);
        $email = mb_strtolower($validated['email']);

        if ($agency->users()->where('email', $email)->exists()) {
            return back()->withErrors(['email' => 'Bu kişi zaten ekipte.']);
        }

        // Aynı adrese yeniden davet: eski satır yenilenir, süresi baştan başlar
        $invitation = $agency->invitations()->updateOrCreate(['email' => $email], [
            'role' => $validated['role'],
            'invited_by' => $request->user()->id,
            'expires_at' => now()->addDays(self::VALID_DAYS),
            'accepted_at' => null,
        ]);

        // Henüz hesabı olmayabilir: bildirim kullanıcıya değil e-posta adresine gider
        Notification::route('mail', $email)->notify(new AgencyInvitationSent($invitation));

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$email} adresine davet gönderildi."]);

        return back();
    }

    public function destroy(CurrentAgency $current, AgencyInvitation $invitation): RedirectResponse
    {
        // Davet modeli ajans kapsamlı değil (bkz. AgencyInvitation): sahipliği açıkça denetliyoruz
        abort_unless($invitation->agency_id === $current->id(), 404);

        $invitation->delete();

        return back();
    }

    /** İmzalı bağlantı: kim olursa olsun önce giriş yapar (auth), sonra daveti görür */
    public function show(Request $request, AgencyInvitation $invitation): Response
    {
        return Inertia::render('invitations/show', [
            'invitation' => [
                'agency' => $invitation->agency->name,
                'inviter' => $invitation->inviter?->name,
                'email' => $invitation->email,
                'role' => $invitation->role->label(),
                'expires_at' => $invitation->expires_at,
            ],
            'pending' => $invitation->isPending(),
            'emailMatches' => $this->emailMatches($request, $invitation),
            // Kabul formu da imzalı adrese gönderilir; imza bağlantının süresiyle aynı anda biter
            'acceptUrl' => URL::temporarySignedRoute('invitations.accept', $invitation->expires_at, $invitation),
        ]);
    }

    public function accept(Request $request, AgencyInvitation $invitation): RedirectResponse
    {
        abort_unless($invitation->isPending(), 410, 'Bu davetin süresi dolmuş ya da kullanılmış.');

        // Bağlantı başkasının eline geçse bile: davet yalnızca o e-postanın sahibine
        abort_unless($this->emailMatches($request, $invitation), 403, 'Bu davet başka bir e-posta adresine gönderildi.');

        $invitation->agency->addMember($request->user(), $invitation->role);
        $invitation->forceFill(['accepted_at' => now()])->save();
        $request->user()->switchAgency($invitation->agency);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$invitation->agency->name} ekibine katıldınız."]);

        return to_route('dashboard');
    }

    private function emailMatches(Request $request, AgencyInvitation $invitation): bool
    {
        return mb_strtolower($request->user()->email) === mb_strtolower($invitation->email);
    }
}
