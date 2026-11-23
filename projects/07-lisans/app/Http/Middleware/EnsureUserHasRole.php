<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Bölüm 4'teki rol middleware'i: ->middleware('role:admin') */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless(in_array($request->user()?->role->value, $roles, true), 403, 'Bu sayfaya erişim yetkiniz yok.');

        return $next($request);
    }
}
