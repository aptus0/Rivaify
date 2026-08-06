<?php

use Illuminate\Support\Facades\Route;

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

// The customer-facing SPA is isolated from the merchant dashboard and is
// resolved by host rather than an exposed store id. The middleware binds the
// host's Store into CurrentStore before any storefront API request or view is
// served. The `.test` route keeps the same behavior available in local tests.
// Same explicit get('/', ...) + fallback() pairing as app.rivaify.com above,
// for the same reason — otherwise the marketing route below wins the exact
// path "/" on any {store}.rivaify.com host too.
Route::domain('{store}.rivaify.com')->middleware('storefront.context')->group(function () {
    Route::get('/', function () {
        return view('storefront');
    });
    Route::fallback(function () {
        return view('storefront');
    });
});


// rivaify.com (and any other/unrecognized host, e.g. plain IP in local dev)
// — the public marketing site.
Route::get('/', function () {
    return view('welcome');
});
