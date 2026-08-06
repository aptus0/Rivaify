<?php

use Illuminate\Support\Facades\Route;

// Merchant dashboard SPA lives on its own subdomain (brief §11) — nginx's
// server_name is `_` (any host), so this Host-based split needs no web
// server config, only a DNS/hosts entry pointing app.rivaify.com at the
// same server. Client-side routing (React Router, basename: '/') owns
// everything under here from here on; see resources/js/dashboard/app/router.
// Registered before the catch-all '/' below so it wins for this host.
Route::domain('app.rivaify.com')->group(function () {
    Route::get('/{any?}', function () {
        return view('dashboard');
    })->where('any', '.*');
});

// rivaify.com (and any other/unrecognized host, e.g. plain IP in local dev)
// — the public marketing site.
Route::get('/', function () {
    return view('welcome');
});
