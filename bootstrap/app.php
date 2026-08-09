<?php

use App\Core\Security\Http\Middleware\EnsureIsRivaifyAdmin;
use App\Core\Security\Http\Middleware\ConfigureHostSession;
use App\Core\Security\Http\Middleware\EnsurePrivateAdminAccess;
use App\Core\Tenancy\Http\Middleware\EnsureStoreContext;
use App\Core\Tenancy\Http\Middleware\EnsureStorefrontStoreContext;
use App\Core\Tenancy\Http\Middleware\EnsureStorePermission;
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
        $middleware->prepend(ConfigureHostSession::class);
        $middleware->statefulApi();

        // The only way this app is reached in any real environment is via
        // Cloudflare Tunnel (cloudflared), which terminates TLS at
        // Cloudflare's edge and forwards to this origin as plain HTTP over
        // loopback — nginx only ever binds 127.0.0.1. Without this, Laravel
        // has no way to know the original request was HTTPS: it builds
        // absolute URLs (redirects, url()/asset()) with an http:// scheme,
        // which browsers then block as mixed content on an https:// page.
        // Trusting '*' is safe here specifically because cloudflared is the
        // only thing that can ever connect to nginx — it isn't bound to a
        // public interface.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'store.context' => EnsureStoreContext::class,
            'store.permission' => EnsureStorePermission::class,
            'storefront.context' => EnsureStorefrontStoreContext::class,
            'rivaify.admin' => EnsureIsRivaifyAdmin::class,
            'private.admin' => EnsurePrivateAdminAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->context(fn (): array => [
            'request_uid' => request()->attributes->get('rivaify_request_uid'),
            'url' => request()->fullUrl(),
        ]);

        $exceptions->shouldRenderJsonWhen(
            // Fortify's headless profile/password routes live outside
            // /api, but the dashboard calls them with Accept: application/json.
            // Respect that negotiation so validation/auth failures never
            // become HTML redirects (there is deliberately no login view).
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
