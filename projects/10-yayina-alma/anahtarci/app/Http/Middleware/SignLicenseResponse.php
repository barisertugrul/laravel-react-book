<?php

namespace App\Http\Middleware;

use App\Support\Signer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lisans API'sinin her yanıtını imzalar: X-License-Signature = base64(Ed25519(gövde)).
 *
 * Neden? Bir site sahibi, hosts dosyasıyla lisans sunucumuzun adresini kendi makinesine
 * yönlendirip her isteğe {"valid": true} döndürebilir. WordPress eklentisi imzayı açık
 * anahtarla doğrularsa sahte sunucunun yanıtı işe yaramaz: imzayı yalnızca biz atabiliriz.
 * Gövdedeki server_time da eski bir "geçerli" yanıtın tekrar kullanılmasını zorlaştırır.
 */
class SignLicenseResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-License-Signature', base64_encode(Signer::sign((string) $response->getContent())));

        return $response;
    }
}
