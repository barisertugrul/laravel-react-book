<?php

namespace App\Http\Middleware;

use App\Tenancy\CurrentAgency;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Hiçbir ajansın üyesi olmayan kullanıcı (ör. son ajansından çıkarılan) önce ajans oluşturur. */
class EnsureAgencySelected
{
    public function __construct(private CurrentAgency $current) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->current->get() === null) {
            return to_route('agencies.create');
        }

        return $next($request);
    }
}
