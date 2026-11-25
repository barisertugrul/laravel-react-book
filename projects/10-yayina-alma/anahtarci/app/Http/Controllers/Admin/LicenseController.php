<?php

namespace App\Http\Controllers\Admin;

use App\Enums\LicenseEventType;
use App\Enums\LicenseStatus;
use App\Http\Controllers\Controller;
use App\Models\License;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LicenseController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q'));
        $status = $request->query('status');

        $licenses = License::query()
            ->with(['user:id,name,email', 'plan.product:id,name'])
            ->withCount('activations')
            ->when(in_array($status, ['active', 'revoked'], true), fn (Builder $q) => $q->where('status', $status))
            ->when($search !== '', fn (Builder $q) => $q->where(function (Builder $q) use ($search) {
                // Müşteri e-postası, adı veya etkinleştirilmiş bir site adresiyle ara
                $q->whereHas('user', fn (Builder $u) => $u->whereLike('email', "%{$search}%")->orWhereLike('name', "%{$search}%"))
                    ->orWhereHas('activations', fn (Builder $a) => $a->whereLike('domain', "%{$search}%"));
            }))
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/licenses/index', [
            'licenses' => $licenses,
            'filters' => ['q' => $search, 'status' => $status],
        ]);
    }

    public function show(License $license): Response
    {
        return Inertia::render('admin/licenses/show', [
            'license' => [
                ...$license->load('user:id,name,email', 'plan.product:id,name,slug', 'order:id,number,total,paid_at,payment_reference')->toArray(),
                'has_updates' => $license->hasUpdates(),
            ],
            'activations' => $license->activations()->latest('last_seen_at')->get(['id', 'domain', 'ip', 'last_seen_at', 'created_at']),
            'events' => $license->events()->limit(50)->get(),
        ]);
    }

    /** İade veya kötüye kullanım: lisans iptal edilir, siteler bir sonraki kontrolde "geçersiz" yanıtı alır */
    public function revoke(Request $request, License $license): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], [], ['reason' => 'iptal nedeni']);

        if ($license->isActive()) {
            $license->update(['status' => LicenseStatus::Revoked, 'revoked_at' => now(), 'revoke_reason' => $data['reason']]);
            $license->log(LicenseEventType::Revoked, meta: ['reason' => $data['reason'], 'by' => $request->user()->email]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Lisans iptal edildi.']);

        return back();
    }

    /** Güncelleme süresini 12 ay uzatır: bitmişse bugünden, bitmemişse bitiş tarihinden itibaren */
    public function extend(Request $request, License $license): RedirectResponse
    {
        $from = $license->hasUpdates() ? $license->updates_until : now();
        $until = $from->addMonths(config('licensing.update_months'));

        $license->update(['updates_until' => $until]);
        $license->log(LicenseEventType::Extended, meta: ['until' => $until->toDateString(), 'by' => $request->user()->email]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Güncelleme süresi '.$until->translatedFormat('j F Y').' tarihine uzatıldı.']);

        return back();
    }
}
