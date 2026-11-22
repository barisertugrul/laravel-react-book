<?php

namespace App\Http\Controllers;

use App\Actions\PlaceOrder;
use App\Http\Requests\CheckoutRequest;
use App\Models\Order;
use App\Payments\PaymentGateway;
use App\Support\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class CheckoutController extends Controller
{
    public function create(Request $request, Cart $cart): Response|RedirectResponse
    {
        $lines = $cart->lines();

        if ($lines->isEmpty()) {
            return to_route('cart.index');
        }

        // Önceki siparişin adresini öner
        $last = $request->user()->orders()->latest()->first();

        return Inertia::render('shop/checkout', [
            'lines' => $lines->map(fn (array $l) => [
                'name' => $l['product']->name,
                'quantity' => $l['quantity'],
                'total' => $l['total'],
            ])->values(),
            'totals' => Cart::totals($lines->sum('total')),
            'defaults' => [
                'shipping_name' => $last->shipping_name ?? $request->user()->name,
                'shipping_phone' => $last->shipping_phone ?? '',
                'shipping_address' => $last->shipping_address ?? '',
                'shipping_city' => $last->shipping_city ?? '',
            ],
        ]);
    }

    public function store(CheckoutRequest $request, Cart $cart, PlaceOrder $place, PaymentGateway $gateway): HttpResponse
    {
        $order = $place->handle($request->user(), $cart, $request->validated(), $gateway->name());

        // Ödeme sayfası başka bir sitede: Inertia::location tarayıcıyı tam sayfa yönlendirir
        return Inertia::location($gateway->start($order));
    }

    /**
     * Ödemeden dönüş sayfası. Siparişi DEĞİŞTİRMEZ; yalnızca durumunu gösterir.
     * Sonuç henüz gelmediyse sayfa birkaç saniyede bir yenilenir (React tarafında usePoll).
     */
    public function result(Request $request, Order $order): Response
    {
        abort_unless($order->user_id === $request->user()->id, 404);

        return Inertia::render('shop/payment-result', [
            'order' => $order->only(['number', 'total', 'status']),
        ]);
    }
}
