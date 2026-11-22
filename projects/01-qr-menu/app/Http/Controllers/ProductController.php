<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('products/index', [
            'products' => Product::query()
                ->with('category:id,name')
                // Önce kategorinin sırası, sonra ürünün kendi sırası
                ->orderBy(Category::select('sort_order')->whereColumn('categories.id', 'products.category_id'))
                ->ordered()
                ->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('products/create', [
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $data = $request->toModelData();

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('products', 'public');
        }

        Product::create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ürün eklendi.']);

        return to_route('products.index');
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('products/edit', [
            'product' => $product,
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->toModelData();

        if ($request->hasFile('image') || $request->boolean('remove_image')) {
            $this->deleteImage($product);
            $data['image_path'] = $request->hasFile('image')
                ? $request->file('image')->store('products', 'public')
                : null;
        }

        $product->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ürün güncellendi.']);

        return to_route('products.index');
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->deleteImage($product);
        $product->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ürün silindi.']);

        return to_route('products.index');
    }

    private function categoryOptions()
    {
        return Category::query()->ordered()->get(['id', 'name']);
    }

    private function deleteImage(Product $product): void
    {
        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }
    }
}
