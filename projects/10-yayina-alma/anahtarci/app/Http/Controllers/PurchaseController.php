<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Plan;
use App\Payments\PaymentGateway;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PurchaseController extends Controller
{
    /** "Satın al": siparişi oluşturur ve müşteriyi ödeme sağlayıcısına gönderir */
    public function store(Request $request, Plan $plan, PaymentGateway $gateway): HttpResponse
    {
        abort_unless($plan->product->is_active, 404);

        $order = $request->user()->orders()->create([
            'plan_id' => $plan->id,
            'status' => OrderStatus::Pending,
            'total' => $plan->price, // fiyat siparişe kopyalanır: plan sonradan değişse de tutar değişmez
            'payment_provider' => $gateway->name(),
        ]);

        // Inertia::location: tarayıcı başka bir siteye (sağlayıcıya) tam sayfa gider
        return Inertia::location($gateway->start($order));
    }

    /**
     * Ödemeden dönüş sayfası. Siparişi "ödendi" YAPMAZ, yalnızca izler (Bölüm 6):
     * webhook gelir → sipariş ödenir → kuyrukta lisans üretilir → sayfa anahtarı gösterir.
     */
    public function result(Request $request, Order $order): Response
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        $order->load('plan.product', 'license');

        return Inertia::render('shop/purchase-result', [
            'order' => [
                'number' => $order->number,
                'status' => $order->status,
                'total' => $order->total,
                'product' => $order->plan->product->name,
                'plan' => $order->plan->name,
            ],
            // Anahtar yalnızca sahibine, kendi sayfasında gösterilir
            'license' => $order->license ? ['id' => $order->license->id, 'key' => $order->license->key] : null,
        ]);
    }
}
