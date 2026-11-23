<?php

namespace App\Http\Controllers;

use App\Enums\HealthStatus;
use App\Models\Site;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        // Site::query() yalnızca etkin ajansın sitelerini getirir (AgencyScope); burada filtre yazmıyoruz
        $sites = Site::query()
            ->orderByRaw("case health_status when 'critical' then 0 when 'warning' then 1 when 'unknown' then 2 else 3 end")
            ->orderBy('name')
            ->get(['id', 'name', 'url', 'health_status', 'last_checked_at', 'wp_version', 'php_version', 'pending_updates', 'response_ms', 'ssl_expires_at']);

        $counts = collect(HealthStatus::cases())
            ->mapWithKeys(fn (HealthStatus $status) => [$status->value => $sites->where('health_status', $status)->count()]);

        return Inertia::render('dashboard', [
            'sites' => $sites,
            'counts' => $counts,
        ]);
    }
}
