<?php

namespace App\Http\Controllers\Billing;

use App\Billing\BillingGateway;
use App\Billing\FakeBillingGateway;
use App\Billing\Plan;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\SubscriptionPayment;
use App\Tenancy\CurrentAgency;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class BillingController extends Controller
{
    public function index(CurrentAgency $current): Response
    {
        $agency = $current->get();
        $subscription = $agency->subscription;

        return Inertia::render('billing/index', [
            'plans' => array_map(fn (Plan $plan) => $plan->toArray(), Plan::all()),
            'current' => $agency->plan()->toArray(),
            'sites' => $agency->siteCount(),
            'demo' => FakeBillingGateway::isDemo(),
            'subscription' => $subscription === null ? null : [
                'plan' => $subscription->plan,
                'status' => $subscription->status->value,
                'status_label' => $subscription->status->label(),
                'card_last4' => $subscription->card_last4,
                'current_period_ends_at' => $subscription->current_period_ends_at->toIso8601String(),
                'grace_ends_at' => $subscription->grace_ends_at?->toIso8601String(),
                'canceled_at' => $subscription->canceled_at?->toIso8601String(),
            ],
            'payments' => SubscriptionPayment::latest('created_at')->latest('id')->limit(12)
                ->get(['id', 'plan', 'amount', 'status', 'reference', 'failure_message', 'created_at']),
        ]);
    }

    /**
     * Plan seçimi. Ücretli plana ilk geçiş (ya da biten aboneliğin yenilenmesi) sağlayıcının ödeme
     * sayfasından geçer; zaten ödeyen bir ajansın ücretli planlar arasındaki geçişi hemen olur,
     * yeni fiyat bir sonraki dönemde uygulanır (kıst hesap yok: bkz. Geliştirme fikirleri).
     */
    public function subscribe(string $plan, CurrentAgency $current, BillingGateway $gateway): HttpResponse
    {
        $plan = Plan::find($plan);
        $agency = $current->get();
        $subscription = $agency->subscription;

        if ($plan->isFree()) {
            return $this->cancel($current);
        }

        if ($subscription !== null && $subscription->status === SubscriptionStatus::Active) {
            $subscription->forceFill(['plan' => $plan->key, 'canceled_at' => null])->save();
            Inertia::flash('toast', ['type' => 'success', 'message' => "{$plan->name} planına geçildi; yeni fiyat bir sonraki dönemde."]);

            return back();
        }

        // Inertia isteği dış bir siteye yönlendirilemez: tam sayfa yönlendirme (409 + X-Inertia-Location)
        return Inertia::location($gateway->checkoutUrl($agency, $plan, 'subscribe'));
    }

    /** İptal: para iadesi yok, dönem sonuna kadar plan sürer, sonra yenilenmez */
    public function cancel(CurrentAgency $current): RedirectResponse
    {
        $subscription = $current->get()->subscription;

        if ($subscription !== null && $subscription->status !== SubscriptionStatus::Ended) {
            $subscription->forceFill(['canceled_at' => now()])->save();
            Inertia::flash('toast', ['type' => 'success', 'message' => 'Abonelik dönem sonunda sona erecek.']);
        }

        return back();
    }

    public function card(CurrentAgency $current, BillingGateway $gateway): HttpResponse
    {
        $agency = $current->get();
        $subscription = $agency->subscription;

        abort_if($subscription === null || $subscription->status === SubscriptionStatus::Ended, 404);

        return Inertia::location($gateway->checkoutUrl($agency, $subscription->plan(), 'card'));
    }
}
