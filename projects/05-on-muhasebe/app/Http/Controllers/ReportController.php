<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceType;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __invoke(): Response
    {
        $today = CarbonImmutable::today();

        // Vadesi geçmiş alacaklar, gecikme süresine göre gruplanmış (yaşlandırma raporu)
        $overdue = Invoice::overdue()
            ->where('type', InvoiceType::Sales)
            ->with('contact:id,name')
            ->orderBy('due_date')
            ->get()
            ->map(fn (Invoice $i) => [
                'id' => $i->id,
                'number' => $i->number,
                'contact' => $i->contact->name,
                'due_date' => $i->due_date->toDateString(),
                'days' => (int) $i->due_date->diffInDays($today),
                'remaining' => $i->remaining,
            ]);

        $buckets = [
            '1-30' => fn ($d) => $d <= 30,
            '31-60' => fn ($d) => $d > 30 && $d <= 60,
            '61-90' => fn ($d) => $d > 60 && $d <= 90,
            '90+' => fn ($d) => $d > 90,
        ];

        return Inertia::render('reports', [
            'monthly' => DashboardController::monthly(12),
            'overdue' => $overdue,
            'aging' => collect($buckets)->map(fn ($test) => $overdue->filter(fn ($i) => $test($i['days']))->sum('remaining')),
        ]);
    }
}
