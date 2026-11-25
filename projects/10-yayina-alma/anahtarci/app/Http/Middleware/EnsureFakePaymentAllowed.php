<?php

namespace App\Http\Middleware;

use App\Payments\FakeGateway;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sahte sağlayıcının sayfaları ve webhook'u yalnızca sahte sağlayıcı kullanılabilirken vardır.
 * Rota önbelleğinden (route:cache) bağımsızdır: karar her istekte config'e göre verilir.
 */
class EnsureFakePaymentAllowed
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(FakeGateway::allowed(), 404);

        return $next($request);
    }
}
