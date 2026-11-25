<?php

namespace App\Http\Controllers;

use App\Enums\LicenseEventType;
use App\Models\Activation;
use App\Models\License;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Müşteri portalı: kendi lisansları ve siteleri */
class LicenseController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('shop/licenses/index', [
            'licenses' => $request->user()->licenses()
                ->with('plan.product:id,name,slug')
                ->withCount('activations')
                ->latest('id')
                ->get(),
        ]);
    }

    public function show(Request $request, License $license): Response
    {
        $this->authorizeOwner($request, $license);

        return Inertia::render('shop/licenses/show', [
            'license' => [
                ...$license->load('plan.product:id,name,slug', 'order:id,number,total,paid_at')->toArray(),
                // #[Hidden] anahtarı toArray()'den çıkarır; sahibine burada BİLEREK ekliyoruz
                'key' => $license->key,
                'has_updates' => $license->hasUpdates(),
            ],
            'activations' => $license->activations()->latest('last_seen_at')->get(['id', 'domain', 'last_seen_at', 'created_at']),
        ]);
    }

    /** Müşteri, artık kullanmadığı (veya erişemediği) bir siteyi kaldırıp yer açar */
    public function destroyActivation(Request $request, License $license, Activation $activation): RedirectResponse
    {
        $this->authorizeOwner($request, $license);
        // Başka bir lisansın aktivasyonu: adres uydurulmuş
        abort_unless($activation->license_id === $license->id, 404);

        $license->log(LicenseEventType::Removed, $activation->domain, $request->ip());
        $activation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$activation->domain} kaldırıldı. O sitede eklenti lisanssız kalacak."]);

        return back();
    }

    /** Başkasının lisansı için 403 değil 404: öyle bir lisansın varlığını bile söylemeyiz */
    private function authorizeOwner(Request $request, License $license): void
    {
        abort_unless($license->user_id === $request->user()->id, 404);
    }
}
