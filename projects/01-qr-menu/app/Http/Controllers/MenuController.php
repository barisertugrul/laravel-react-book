<?php

namespace App\Http\Controllers;

use App\Models\Category;
use Inertia\Inertia;
use Inertia\Response;

class MenuController extends Controller
{
    /** Müşterinin QR kodu okuttuğunda gördüğü herkese açık menü. */
    public function __invoke(): Response
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->whereHas('products', fn ($query) => $query->where('is_available', true))
            ->with(['products' => fn ($query) => $query->where('is_available', true)->ordered()])
            ->ordered()
            ->get();

        return Inertia::render('menu', [
            'restaurant' => config('app.name'),
            'categories' => $categories,
        ]);
    }
}
