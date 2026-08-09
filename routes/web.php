<?php

use App\Core\Security\Http\Controllers\InternalAuthController;
use App\Core\Tenancy\CurrentStore;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Merchant dashboard SPA lives on its own subdomain (brief §11) — nginx's
// server_name is `_` (any host), so this Host-based split needs no web
// server config, only a DNS/hosts entry pointing app.rivaify.com at the
// same server. Client-side routing (React Router, basename: '/') owns
// everything under here from here on; see resources/js/dashboard/app/router.
// Registered before the catch-all '/' below so it wins for this host.
// fallback(), not get('/{any?}'): api/* and sanctum/* are registered
// elsewhere (routes/api.php, Sanctum's own service provider) with no
// domain constraint, and web.php's routes are compiled before api.php's —
// an explicit get('/{any?}') would win the match for app.rivaify.com/api/me
// regardless of a negative-lookahead ->where() (verified: Symfony's
// compiled route regex doesn't respect ^/$ anchors placed inside a
// parameter's own pattern the way a standalone preg_match does). fallback()
// sidesteps the ordering problem entirely — it only ever fires when nothing
// else matched. Bug found live 2026-08-06 via a crashing AuthProvider
// (GET /api/me came back as this dashboard HTML instead of JSON,
// "Cannot read properties of undefined (reading 'data')").
//
// The explicit get('/', ...) below the fallback is a second, later bug fix:
// Laravel checks *every* non-fallback route before *any* fallback route,
// regardless of domain specificity or registration order — so the
// domain-less marketing get('/', ...) further down this file was winning
// the exact path "/" for app.rivaify.com too (fallback() only ever runs
// when nothing else matched at all). An explicit domain-scoped get('/', ...)
// is a normal route like the marketing one, so registration order decides
// the tie-break, and this one comes first. Found live 2026-08-06: bare
// https://app.rivaify.com/ served the marketing page while /login worked
// fine (nothing else matches that exact path, so it reached the fallback).
Route::domain('app.rivaify.com')->group(function () {
    Route::get('/', function () {
        return view('dashboard');
    });
    Route::fallback(function () {
        return view('dashboard');
    });
});


// Rivaify internal operations. The host can resolve publicly, but the
// surface is still staff-only: host-only hardened session cookie, CSRF,
// throttled login, auth:sanctum for API calls, and rivaify.admin RBAC on
// internal APIs.
Route::domain('ins.rivaify.com')->group(function () {
    // Own login, not Fortify's (which is domain-locked to app.rivaify.com,
    // see config/fortify.php) — see InternalAuthController's docblock for
    // why this can't just reuse the merchant dashboard's session.
    Route::post('/login', [InternalAuthController::class, 'login'])->middleware('throttle:6,1');
    Route::post('/logout', [InternalAuthController::class, 'logout'])->middleware('auth:sanctum');

    Route::get('/', function () {
        return view('internal-admin');
    });
    Route::fallback(function () {
        return view('internal-admin');
    });
});

// The customer-facing SPA is isolated from the merchant dashboard and is
// resolved by host rather than an exposed store id. The middleware binds the
// host's Store into CurrentStore before any storefront API request or view is
// served. Same explicit get('/', ...) + fallback() pairing as app.rivaify.com above,
// for the same reason — otherwise the marketing route below wins the exact
// path "/" on any {store}.rivaify.com host too.
Route::domain('{store}.rivaify.com')->middleware('storefront.context')->group(function () {
    Route::get('/', function () {
        return view('storefront', ['store' => app(CurrentStore::class)->store()]);
    });
    Route::fallback(function () {
        return view('storefront', ['store' => app(CurrentStore::class)->store()]);
    });
});

Route::domain('{store}.rivaify.localhost')->middleware('storefront.context')->group(function () {
    Route::get('/', function () {
        return view('storefront', ['store' => app(CurrentStore::class)->store()]);
    });
    Route::fallback(function () {
        return view('storefront', ['store' => app(CurrentStore::class)->store()]);
    });
});

// Verified merchant-owned domains use the same storefront shell. The host
// constraint deliberately excludes every Rivaify-owned hostname; those are
// handled by the explicit routes above or the marketing site below.
Route::domain('{customDomain}')
    ->middleware('storefront.context')
    ->group(function () {
        $customDomainPattern = '(?!(?:.+\\.)?rivaify\\.(?:com|test|localhost)$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\\.)+[a-z0-9][a-z0-9-]{0,62}';

        Route::get('/', function () {
            return view('storefront', ['store' => app(CurrentStore::class)->store()]);
        })->where('customDomain', $customDomainPattern);

        Route::fallback(function () {
            return view('storefront', ['store' => app(CurrentStore::class)->store()]);
        })->where('customDomain', $customDomainPattern);
    });

// rivaify.com (and any other/unrecognized host, e.g. plain IP in local dev)
// — the public marketing site. Trimmed 2026-08-09 from an earlier 15-page
// pass down to 4 deliberately-kept pages (Home/Platform/Store Builder/
// Pricing); each a real Laravel route rendering an Inertia page so search
// engines / OG scrapers
// get correct per-route <title>/<meta> in the *first* HTML response — see
// resources/views/app.blade.php for how the `seo` prop below reaches that
// HTML without needing an Inertia SSR process. HandleInertiaRequests is
// scoped to just this group (not the global 'web' middleware stack) so it
// can never affect the dashboard/storefront Blade-only routes above.
Route::middleware([HandleInertiaRequests::class])->group(function () {
    Route::get('/', fn () => Inertia::render('Marketing/Home', [
        'seo' => [
            'title' => 'Rivaify | Yeni Nesil E-Ticaret Platformu',
            'description' => 'Rivaify ile online mağazanı kur, ürünlerini ve siparişlerini yönet, sosyal satış kanallarını tek platform üzerinden yönet.',
            'canonical' => 'https://rivaify.com/',
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                'name' => 'Rivaify',
                'url' => 'https://rivaify.com/',
                'logo' => 'https://rivaify.com/og-image.png',
                'description' => 'Rivaify ile online mağazanı kur, ürünlerini ve siparişlerini yönet, sosyal satış kanallarını tek platform üzerinden yönet.',
            ],
        ],
    ]))->name('marketing.home');

    Route::get('/platform', fn () => Inertia::render('Marketing/Platform', [
        'seo' => [
            'title' => 'Rivaify Platform | Ticaret İşletim Sistemi',
            'description' => 'Ürünlerden siparişlere, müşterilerden analitiğe — Rivaify ticaret operasyonunun tamamını tek platformda birleştirir.',
            'canonical' => 'https://rivaify.com/platform',
        ],
    ]))->name('marketing.platform');

    Route::get('/store-builder', fn () => Inertia::render('Marketing/StoreBuilder', [
        'seo' => [
            'title' => 'Rivaify Store Builder | Sürükle-Bırak Mağaza Tasarımı',
            'description' => 'Kod yazmadan, sürükle-bırak sayfa oluşturucuyla markana ait bir mağaza tasarla.',
            'canonical' => 'https://rivaify.com/store-builder',
        ],
    ]))->name('marketing.store-builder');

    Route::get('/pricing', fn () => Inertia::render('Marketing/Pricing', [
        'seo' => [
            'title' => 'Rivaify Fiyatlandırma | Satış Yaptıkça Büyüyen Model',
            'description' => 'Rivaify erken erişim fiyatlandırması ve ticari model hakkında bilgi al.',
            'canonical' => 'https://rivaify.com/pricing',
        ],
    ]))->name('marketing.pricing');
});
