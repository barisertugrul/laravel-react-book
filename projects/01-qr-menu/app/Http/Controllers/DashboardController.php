<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('dashboard', [
            'stats' => [
                'categories' => Category::count(),
                'products' => Product::count(),
                'unavailable' => Product::where('is_available', false)->count(),
            ],
        ]);
    }
}
