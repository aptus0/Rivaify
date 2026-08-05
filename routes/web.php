<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Merchant dashboard SPA (brief §11: app.rivaify.com in production).
// Served under a path prefix here since local dev has no subdomain DNS —
// client-side routing (React Router, basename: '/app') owns everything
// under this prefix from here on. See resources/js/dashboard/app/router.
Route::get('/app/{any?}', function () {
    return view('dashboard');
})->where('any', '.*');
