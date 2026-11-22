<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Notifications\OrderShipped;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $status = OrderStatus::tryFrom((string) $request->query('status')) ?? OrderStatus::Paid;

        return Inertia::render('admin/orders/index', [
            'orders' => Order::where('status', $status)
                ->with('user:id,name')
                ->withCount('items')
                ->latest()
                ->paginate(20)
                ->withQueryString(),
            'status' => $status,
            // Sekmelerdeki sayılar: durum başına tek sorgu yerine tek GROUP BY
            'counts' => Order::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function show(Order $order): Response
    {
        return Inertia::render('admin/orders/show', [
            'order' => $order->load(['items', 'user:id,name,email']),
        ]);
    }

    /** Kargoya ver: yalnızca ödenmiş sipariş */
    public function ship(Request $request, Order $order): RedirectResponse
    {
        abort_unless($order->status === OrderStatus::Paid, 422, 'Yalnızca ödenmiş siparişler kargoya verilebilir.');

        $validated = $request->validate(['tracking_number' => ['required', 'string', 'max:50']]);

        $order->update([...$validated, 'status' => OrderStatus::Shipped, 'shipped_at' => now()]);
        $order->user->notify(new OrderShipped($order));

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$order->number} kargoya verildi."]);

        return back();
    }
}
