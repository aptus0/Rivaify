<?php

use App\Core\Security\Http\Middleware\EnsureIsRivaifyAdmin;
use App\Core\Tenancy\Http\Middleware\EnsureStoreContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Cookie-based auth for the first-party React SPA(s) — app./admin.
        // rivaify.com talking to api.rivaify.com — see SANCTUM_STATEFUL_DOMAINS.
        $middleware->statefulApi();

        $middleware->alias([
            'store.context' => EnsureStoreContext::class,
            'rivaify.admin' => EnsureIsRivaifyAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
