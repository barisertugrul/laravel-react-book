<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route'u yalnızca belirli rollere açar:
 *   Route::middleware('role:admin')
 *   Route::middleware('role:admin,technician')
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless(in_array($request->user()?->role->value, $roles, true), 403, 'Bu sayfaya erişim yetkiniz yok.');

        return $next($request);
    }
}
