<?php

namespace App\Http\Controllers;

use App\Models\Package;
use App\Models\Site;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * WordPress sitesinin paketi indirdiği adres. Oturum YOK: isteği WordPress yapar.
 * Yetkiyi adresin imzası verir ('signed' middleware): paket ve site numarası + bitiş zamanı
 * APP_KEY ile imzalı, biri değişirse imza tutmaz (403).
 *
 * Oturum olmadığı için ajans bağlamı da yok; AgencyScope sorguyu boş döndürür (fail-closed).
 * Burada kapsamı BİLEREK kaldırıyoruz (acrossAgencies): ajansı imzalı adres belirledi.
 */
class PackageDownloadController extends Controller
{
    public function __invoke(int $package, int $site): StreamedResponse
    {
        $package = Package::acrossAgencies()->findOrFail($package);

        // Paket ve site aynı ajansın mı? İmza zaten bunu garanti eder; ikinci kilit ucuzdur.
        Site::acrossAgencies()->where('agency_id', $package->agency_id)->findOrFail($site);

        return Storage::disk('local')->download($package->path, "{$package->slug}.zip", [
            'Content-Type' => 'application/zip',
            'Cache-Control' => 'no-store',
        ]);
    }
}
