<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Support\Cart;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function index(Cart $cart): Response
    {
        $lines = $cart->lines();

        return Inertia::render('shop/cart', [
            'lines' => $lines->map(fn (array $line) => [
                'product' => $line['product']->only(['id', 'name', 'slug', 'price', 'stock']),
                'quantity' => $line['quantity'],
                'total' => $line['total'],
                'in_stock' => $line['in_stock'],
            ])->values(),
            'totals' => Cart::totals($lines->sum('total')),
            'freeShippingFrom' => config('shop.free_shipping_from'),
        ]);
    }

    /** Sepete ekle. Dikkat: istekten yalnızca ürün ve adet alınır, fiyat asla. */
    public function store(Request $request, Cart $cart): RedirectResponse
    {
        $validated = $request->validate([
            'product_id' => ['required', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $product = Product::visible()->findOrFail($validated['product_id']);

        if ($product->stock < 1) {
            return back()->withErrors(['quantity' => 'Bu ürün tükendi.']);
        }

        $cart->add($product, $validated['quantity']);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$product->name} sepete eklendi."]);

        return back();
    }

    public function update(Request $request, Product $product, Cart $cart): RedirectResponse
    {
        $validated = $request->validate(['quantity' => ['required', 'integer', 'min:0', 'max:20']]);

        $cart->set($product, $validated['quantity']);

        return back();
    }

    public function destroy(Product $product, Cart $cart): RedirectResponse
    {
        $cart->remove($product);

        return back();
    }
}
