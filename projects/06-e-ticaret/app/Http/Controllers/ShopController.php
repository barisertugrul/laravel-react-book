<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ShopController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('shop/index', [
            'products' => Product::visible()
                ->when($request->query('q'), fn ($q, $term) => $q->where('name', 'like', "%{$term}%"))
                ->orderBy('name')
                ->paginate(12, ['id', 'name', 'slug', 'price', 'stock'])
                ->withQueryString(),
            'filters' => $request->only('q'),
        ]);
    }

    public function show(Product $product): Response
    {
        // Satıştan kaldırılmış ürün, adresi bilinse bile görünmez
        abort_unless($product->is_active, 404);

        return Inertia::render('shop/product', [
            'product' => $product->only(['id', 'name', 'slug', 'description', 'price', 'stock']),
        ]);
    }
}
