<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PlanRequest;
use App\Models\Plan;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Bir ürünün satış seçenekleri. Fiyat ya da site sınırı değişince satılmış lisanslar ETKİLENMEZ:
 * sipariş tutarı (orders.total) ve lisansın site sınırı (licenses.max_sites) satın alma anında kopyalandı.
 */
class PlanController extends Controller
{
    public function store(PlanRequest $request, Product $product): RedirectResponse
    {
        $product->plans()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plan eklendi.']);

        return back();
    }

    public function update(PlanRequest $request, Plan $plan): RedirectResponse
    {
        $plan->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plan kaydedildi. Daha önce satılan lisanslar değişmedi.']);

        return back();
    }

    /** Yalnızca hiç sipariş almamış plan silinir; satılmış planı kaldırmak için ürünü satıştan kaldırın */
    public function destroy(Plan $plan): RedirectResponse
    {
        if ($plan->orders()->exists()) {
            throw ValidationException::withMessages(['plan' => 'Bu plan için sipariş var; silinemez.']);
        }

        $plan->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Plan silindi.']);

        return back();
    }
}
