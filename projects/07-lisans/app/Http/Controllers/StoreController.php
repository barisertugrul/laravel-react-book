<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Inertia\Inertia;
use Inertia\Response;

/** Vitrin: satılan eklentiler ve planları */
class StoreController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('shop/index', [
            'products' => Product::where('is_active', true)
                ->with('plans:id,product_id,name,price,max_sites')
                ->orderBy('id')
                ->get(['id', 'name', 'slug', 'summary', 'description']),
        ]);
    }
}
