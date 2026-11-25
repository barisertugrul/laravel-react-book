<?php

namespace App\Http\Middleware;

use App\Tenancy\CurrentAgency;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Giriş yapmış kullanıcının etkin ajansını isteğin bağlamına koyar.
 *
 * SIRA ÖNEMLİ: route model binding'den (SubstituteBindings) ÖNCE çalışmalıdır; yoksa
 * {site} parametresi ajans filtresi kurulmadan çözülür (bkz. bootstrap/app.php).
 */
class SetCurrentAgency
{
    public function __construct(private CurrentAgency $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $agency = null;

        if ($user !== null) {
            $agency = $user->currentAgency;

            // Hiç seçilmemişse ya da kullanıcı o ajanstan çıkarılmışsa: üyesi olduğu ilk ajans
            if ($agency === null || ! $user->belongsToAgency($agency)) {
                $agency = $user->agencies()->orderBy('agencies.id')->first();
                $user->forceFill(['current_agency_id' => $agency?->id])->save();
            }
        }

        // Misafirde de çağrılır: bağlam her istekte baştan kurulur
        $this->current->set($agency);

        return $next($request);
    }
}
