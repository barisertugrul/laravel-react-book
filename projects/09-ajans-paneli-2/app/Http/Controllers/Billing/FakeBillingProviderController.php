<?php

namespace App\Http\Controllers\Billing;

use App\Billing\FakeBillingGateway;
use App\Billing\Plan;
use App\Http\Controllers\Controller;
use App\Jobs\SendFakeBillingWebhook;
use App\Models\Agency;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bu controller UYGULAMAMIZIN değil, SAHTE SAĞLAYICININ parçasıdır (Bölüm 6'daki gibi).
 * Gerçekte bu sayfa sağlayıcının sitesinde olurdu ve kart numarası bize hiç gelmezdi.
 * Aboneliği kendisi açmaz: sonucu imzalı bir webhook ile bildirir.
 */
class FakeBillingProviderController extends Controller
{
    public function show(Request $request): Response
    {
        [$agency, $plan, $purpose] = $this->context($request);

        return Inertia::render('fake-billing', [
            'merchant' => config('app.name'),
            'agency' => $agency->name,
            'plan' => $plan->toArray(),
            'purpose' => $purpose,
            'cards' => FakeBillingGateway::CARDS,
            'action' => $request->fullUrl(),
            'csrf' => csrf_token(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$agency, $plan, $purpose] = $this->context($request);
        $card = $request->validate(['card' => ['required', Rule::in(FakeBillingGateway::CARDS)]])['card'];

        // 0002 ilk ödemede de reddedilir; kart değişikliğinde (purpose=card) para çekilmez, kart yine reddedilir
        $declined = $card === '0002';

        SendFakeBillingWebhook::dispatch([
            'event_id' => (string) Str::uuid(),
            'type' => $declined ? 'checkout.failed' : 'checkout.completed',
            'purpose' => $purpose,
            'agency_id' => $agency->id,
            'plan' => $plan->key,
            'amount' => $purpose === 'subscribe' ? $plan->price : 0,
            'card_token' => $declined ? null : FakeBillingGateway::token($card),
            'card_last4' => $card,
            'reference' => $declined || $purpose === 'card' ? null : 'FAKE-'.Str::upper(Str::random(10)),
            'message' => $declined ? 'Kart reddedildi.' : null,
        ]);

        // Müşteriyi panele geri gönder. Bu yönlendirme "ödendi" bilgisi TAŞIMAZ; abonelik webhook'la açılır.
        return redirect()->route('billing.index', ['donus' => $declined ? 'red' : 'tamam']);
    }

    /** @return array{0: Agency, 1: Plan, 2: string} */
    private function context(Request $request): array
    {
        return [
            Agency::findOrFail($request->integer('agency')),
            Plan::find((string) $request->query('plan')),
            $request->query('purpose') === 'card' ? 'card' : 'subscribe',
        ];
    }
}
