<?php

namespace App\Http\Controllers;

use App\Enums\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Plan değiştirme ekranı. Bu bölümde ödeme almıyoruz; ödeme entegrasyonu
 * Bölüm 6 (iyzico/Stripe) ve Bölüm 9'da (Laravel Cashier) gelecek.
 */
class PlanController extends Controller
{
    public function show(Request $request): Response
    {
        return Inertia::render('plan', [
            'plans' => array_map(fn (Plan $plan) => $plan->toArray(), Plan::cases()),
            'current' => $request->user()->plan->value,
            'cardCount' => $request->user()->cards()->count(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'plan' => ['required', Rule::enum(Plan::class)],
        ]);

        $user = $request->user();
        $plan = Plan::from($validated['plan']);

        // Planı düşürürken mevcut kart sayısı yeni sınırı aşmamalı.
        if ($user->cards()->count() > $plan->cardLimit()) {
            throw ValidationException::withMessages([
                'plan' => "{$plan->label()} plana geçmek için önce kart sayınızı {$plan->cardLimit()} adede indirin.",
            ]);
        }

        $user->update(['plan' => $plan]);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Planınız: {$plan->label()}"]);

        return to_route('plan.show');
    }
}
