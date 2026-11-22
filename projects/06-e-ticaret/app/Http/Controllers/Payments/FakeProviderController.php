<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Jobs\SendFakeWebhook;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bu controller UYGULAMAMIZIN değil, SAHTE SAĞLAYICININ parçasıdır.
 * Gerçek hayatta bu sayfa iyzico'nun, PayTR'nin sunucusunda olurdu.
 * Bu yüzden siparişi kendisi güncellemez; sonucu webhook olarak bildirir.
 */
class FakeProviderController extends Controller
{
    public function show(Order $order): Response
    {
        return Inertia::render('fake-provider', [
            'order' => $order->only(['number', 'total']),
            'merchant' => config('app.name'),
            // Form, aynı imzalı adrese gönderilir. Düz HTML formu olduğu için CSRF jetonunu biz veriyoruz.
            'action' => request()->fullUrl(),
            'csrf' => csrf_token(),
        ]);
    }

    public function store(Request $request, Order $order): RedirectResponse
    {
        $approved = $request->input('result') === 'approve';

        SendFakeWebhook::dispatch([
            'event_id' => (string) Str::uuid(),
            'type' => $approved ? 'payment.succeeded' : 'payment.failed',
            'order' => $order->number,
            'amount' => $order->total,
            'reference' => 'FAKE-'.Str::upper(Str::random(10)),
        ]);

        // Müşteriyi mağazaya geri gönder. Dikkat: bu yönlendirme "ödendi" BİLGİSİ taşımaz.
        return redirect()->route('checkout.result', $order);
    }
}
