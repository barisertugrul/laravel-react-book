<?php

use App\Http\Middleware\EnsureAgencySelected;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetCurrentAgency;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Sağlayıcının bildirimi tarayıcıdan gelmez: CSRF jetonu yok, kimliği imzası kanıtlar
        $middleware->validateCsrfTokens(except: ['webhooks/*']);

        $middleware->alias([
            'agency' => EnsureAgencySelected::class,
        ]);

        $middleware->web(append: [
            HandleAppearance::class,
            SetCurrentAgency::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // web grubuna sona eklenen middleware, grubun başındaki SubstituteBindings'ten SONRA çalışır.
        // Öncelik listesi bu sırayı düzeltir: etkin ajans, {site} çözülmeden önce belirlenir.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: SetCurrentAgency::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
