<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('categories/index', [
            'categories' => Category::query()
                ->withCount('products')
                ->ordered()
                ->get(),
        ]);
    }

    public function store(CategoryRequest $request): RedirectResponse
    {
        Category::create($request->toModelData());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kategori eklendi.']);

        return to_route('categories.index');
    }

    public function update(CategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->toModelData());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kategori güncellendi.']);

        return to_route('categories.index');
    }

    public function destroy(Category $category): RedirectResponse
    {
        // Ürün satırlarını veritabanı siler (cascadeOnDelete); ama veritabanı diskteki
        // dosyalardan habersizdir. Görselleri kayıtlar silinmeden önce biz siliyoruz.
        $paths = $category->products()->whereNotNull('image_path')->pluck('image_path');
        Storage::disk('public')->delete($paths->all());

        $category->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kategori silindi.']);

        return to_route('categories.index');
    }
}
