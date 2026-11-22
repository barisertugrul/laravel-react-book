<?php

namespace App\Http\Controllers;

use App\Enums\InvoiceType;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        // sum() bir sütun adı yerine SQL ifadesi de alabilir: SUM(total - paid_total)
        $remaining = DB::raw('total - paid_total');
        $outstanding = fn (InvoiceType $type) => (int) Invoice::outstanding()->where('type', $type)->sum($remaining);

        return Inertia::render('dashboard', [
            'summary' => [
                'receivable' => $outstanding(InvoiceType::Sales),    // alacaklarımız
                'payable' => $outstanding(InvoiceType::Purchase),    // borçlarımız
                'overdue_count' => Invoice::overdue()->where('type', InvoiceType::Sales)->count(),
                'overdue_amount' => (int) Invoice::overdue()->where('type', InvoiceType::Sales)->sum($remaining),
            ],
            'monthly' => self::monthly(6),
        ]);
    }

    /**
     * Son N ayın satış ve alış toplamları (KDV dahil, iptaller hariç).
     * Gruplamayı PHP'de yapıyoruz: SQL'de ay çıkarma fonksiyonu veritabanına göre değişir
     * (SQLite: strftime, MySQL: DATE_FORMAT, PostgreSQL: to_char).
     *
     * @return list<array{month: string, sales: int, purchase: int}>
     */
    public static function monthly(int $months): array
    {
        $start = CarbonImmutable::today()->startOfMonth()->subMonths($months - 1);

        $totals = Invoice::query()
            ->whereNull('cancelled_at')
            ->where('issue_date', '>=', $start)
            ->get(['type', 'issue_date', 'total'])
            ->groupBy(fn (Invoice $i) => $i->issue_date->format('Y-m'));

        return collect(range(0, $months - 1))
            ->map(fn (int $i) => $start->addMonths($i)->format('Y-m'))
            ->map(fn (string $month) => [
                'month' => $month,
                'sales' => (int) ($totals[$month] ?? collect())->where('type', InvoiceType::Sales)->sum('total'),
                'purchase' => (int) ($totals[$month] ?? collect())->where('type', InvoiceType::Purchase)->sum('total'),
            ])
            ->all();
    }
}
