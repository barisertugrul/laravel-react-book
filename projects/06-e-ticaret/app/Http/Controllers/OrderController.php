<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Müşterinin kendi siparişleri */
class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('shop/orders/index', [
            'orders' => $request->user()->orders()
                ->withCount('items')
                ->latest()
                ->paginate(10),
        ]);
    }

    public function show(Request $request, Order $order): Response
    {
        // Başkasının siparişi için 403 değil 404: o numarada bir sipariş olduğunu bile söylemeyiz
        abort_unless($order->user_id === $request->user()->id, 404);

        return Inertia::render('shop/orders/show', ['order' => $order->load('items')]);
    }
}
