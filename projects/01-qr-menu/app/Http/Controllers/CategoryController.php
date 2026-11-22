<?php

namespace App\Http\Controllers;

use App\Http\Requests\CategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
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
        // Ürünler, migration'daki cascadeOnDelete sayesinde birlikte silinir.
        $category->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kategori silindi.']);

        return to_route('categories.index');
    }
}
