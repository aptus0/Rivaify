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
Route::domain('app.rivaify.com')->group(function () {
    Route::fallback(function () {
        return view('dashboard');
    });
});

// rivaify.com (and any other/unrecognized host, e.g. plain IP in local dev)
// — the public marketing site.
Route::get('/', function () {
    return view('welcome');
});
