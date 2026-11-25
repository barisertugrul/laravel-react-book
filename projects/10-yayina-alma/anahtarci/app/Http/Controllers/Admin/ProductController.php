<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Ürün ve plan yönetimi (Bölüm 10: canlıya alırken eksik olduğu fark edildi; Bölüm 7'de ürünler
 * yalnızca seeder ile geliyordu). Ürün SİLİNMEZ: satılmış lisanslar ona bağlı. Satıştan kaldırılır.
 */
class ProductController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/products/index', [
            'products' => Product::withCount(['plans', 'licenses'])->orderBy('name')->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/products/form');
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $product = Product::create($request->validated());

        // Yeni ürünün henüz planı yok: plan eklemek için düzenleme sayfasına
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ürün oluşturuldu. Şimdi en az bir plan ekleyin.']);

        return to_route('admin.products.edit', $product);
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('admin/products/form', [
            'product' => $product->loadCount('licenses'),
            'plans' => $product->plans()->withCount(['orders', 'licenses'])->get(),
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ürün kaydedildi.']);

        return back();
    }
}
