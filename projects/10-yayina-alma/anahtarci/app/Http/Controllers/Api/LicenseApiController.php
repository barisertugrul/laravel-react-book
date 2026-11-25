<?php

namespace App\Http\Controllers\Api;

use App\Actions\ActivateLicense;
use App\Enums\LicenseEventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\ActivateLicenseRequest;
use App\Models\Activation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * WordPress eklentisinin konuştuğu API (Bölüm 8).
 * activate: anahtar + site adresi → siteye özel token
 * check / deactivate: o token'la (Authorization: Bearer ...)
 */
class LicenseApiController extends Controller
{
    public function activate(ActivateLicenseRequest $request, ActivateLicense $activate): JsonResponse
    {
        ['activation' => $activation, 'token' => $token, 'created' => $created] = $activate->handle(
            $request->validated('key'),
            $request->validated('domain'),
            $request->ip(),
        );

        return response()->json([
            'token' => $token,
            'domain' => $activation->domain,
            'license' => $activation->license->toApiArray(),
            'server_time' => now()->toIso8601String(),
        ], $created ? 201 : 200);
    }

    /** Eklenti bunu günde bir kez çağırır: lisans hâlâ geçerli mi, güncelleme hakkı sürüyor mu? */
    public function check(Request $request): JsonResponse
    {
        $activation = $this->activation($request);

        $activation->update(['last_seen_at' => now(), 'ip' => $request->ip()]);

        return response()->json([
            'domain' => $activation->domain,
            // valid, lisansın veritabanındaki GÜNCEL durumundan hesaplanır (bkz. License::toApiArray)
            'license' => $activation->license->toApiArray(),
            'server_time' => now()->toIso8601String(),
        ]);
    }

    /** Eklenti kaldırılırken veya site taşınırken yer boşaltılır */
    public function deactivate(Request $request): JsonResponse
    {
        $activation = $this->activation($request);

        $activation->license->log(LicenseEventType::Deactivated, $activation->domain, $request->ip());
        $activation->delete(); // token'ı da siler (bkz. Activation::booted)

        return response()->json(['deactivated' => true, 'server_time' => now()->toIso8601String()]);
    }

    /**
     * auth:sanctum, token'ın sahibini döndürür. Uygulamada token alabilen tek model Activation'dır,
     * ama ileride kullanıcılara da token verilirse o token'larla bu uçlar çağrılamasın.
     */
    private function activation(Request $request): Activation
    {
        $activation = $request->user();

        abort_unless($activation instanceof Activation && $activation->tokenCan('license:check'), 403, 'Bu token lisans API\'si için değil.');

        return $activation;
    }
}
