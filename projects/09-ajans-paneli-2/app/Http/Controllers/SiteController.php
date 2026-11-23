<?php

namespace App\Http\Controllers;

use App\Actions\PairSite;
use App\Actions\RepairSite;
use App\Agent\AgentException;
use App\Billing\PlanLimitReached;
use App\Http\Requests\RepairSiteRequest;
use App\Http\Requests\StoreSiteRequest;
use App\Jobs\CheckSiteHealth;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class SiteController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('sites/create');
    }

    public function store(StoreSiteRequest $request, PairSite $pair): RedirectResponse
    {
        try {
            $site = $pair->handle($request->validated('name'), $request->connectionKey());
        } catch (AgentException|PlanLimitReached $e) {
            return back()->withErrors(['connection_key' => $e->getMessage()])->withInput();
        }

        // İlk kontrolü hemen kuyruğa koy: kullanıcı site sayfasında sonucu birkaç saniyede görür
        CheckSiteHealth::dispatch($site);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$site->name} bağlandı; ilk kontrol başladı."]);

        return to_route('sites.show', $site);
    }

    /**
     * {site} parametresi AgencyScope'lu sorguyla çözülür: başka ajansın sitesi 404 olur.
     * (Bunun için SetCurrentAgency'nin binding'den önce çalışması gerekir; bkz. bootstrap/app.php.)
     */
    public function show(Site $site): Response
    {
        $checks = $site->checks()->latest('checked_at')->limit(48)
            ->get(['id', 'status', 'http_status', 'response_ms', 'ssl_expires_at', 'issues', 'error', 'checked_at']);

        return Inertia::render('sites/show', [
            'site' => $site->only(['id', 'name', 'url', 'health_status', 'status_changed_at', 'last_checked_at', 'wp_version', 'php_version', 'agent_version', 'pending_updates', 'response_ms', 'ssl_expires_at', 'created_at']),
            'checks' => $checks,
            'report' => $site->checks()->whereNotNull('report')->latest('checked_at')->first(['report'])?->report,
        ]);
    }

    /** Bağlantıyı yenile: aynı kayıt, yeni anahtarlar (bkz. RepairSite) */
    public function repair(RepairSiteRequest $request, Site $site, RepairSite $repair): RedirectResponse
    {
        try {
            $repair->handle($site, $request->connectionKey());
        } catch (AgentException $e) {
            return back()->withErrors(['connection_key' => $e->getMessage()]);
        }

        CheckSiteHealth::dispatch($site);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$site->name} yeniden bağlandı; kontrol başladı."]);

        return to_route('sites.show', $site);
    }

    public function destroy(Site $site): RedirectResponse
    {
        $site->delete(); // site_checks cascade ile silinir

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$site->name} panelden kaldırıldı."]);

        return to_route('dashboard');
    }
}
