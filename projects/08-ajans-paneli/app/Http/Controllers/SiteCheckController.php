<?php

namespace App\Http\Controllers;

use App\Jobs\CheckSiteHealth;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

/** "Şimdi kontrol et": işi kuyruğa koyar; sayfa sonucu yoklayarak (polling) gösterir */
class SiteCheckController extends Controller
{
    public function __invoke(Site $site): RedirectResponse
    {
        CheckSiteHealth::dispatch($site);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kontrol kuyruğa kondu.']);

        return back();
    }
}
