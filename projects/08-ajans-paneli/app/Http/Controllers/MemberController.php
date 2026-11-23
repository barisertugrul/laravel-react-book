<?php

namespace App\Http\Controllers;

use App\Enums\AgencyRole;
use App\Models\User;
use App\Tenancy\CurrentAgency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MemberController extends Controller
{
    /** Üyeyi ETKİN ajanstan çıkarır; kullanıcının hesabı ve diğer ajanslardaki üyeliği durur */
    public function destroy(Request $request, CurrentAgency $current, User $user): RedirectResponse
    {
        $agency = $current->get();

        // User modeli ajans kapsamlı değil: üyeliği kendimiz denetliyoruz
        $role = $user->roleIn($agency);
        abort_if($role === null, 404);

        if ($user->is($request->user())) {
            return back()->withErrors(['member' => 'Kendinizi ajanstan çıkaramazsınız.']);
        }

        if ($role === AgencyRole::Owner && count($agency->owners()) === 1) {
            return back()->withErrors(['member' => 'Ajansın son sahibi çıkarılamaz.']);
        }

        $agency->users()->detach($user->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$user->name} ajanstan çıkarıldı."]);

        return back();
    }
}
