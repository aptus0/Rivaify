<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Tells the SPA where to route the user after login: no merchant yet ->
// store-creation step; merchant+store exist -> resume at their current
// onboarding_status; onboarding_status is Completed -> dashboard.
Route::get('/me', function (Request $request) {
    $user = $request->user();
    $merchant = $user->merchant()->with('stores')->first();
    $store = $merchant?->stores->first();

    return response()->json([
        'data' => [
            'user' => [
                'id' => $user->ulid,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified' => $user->hasVerifiedEmail(),
            ],
            'store' => $store ? [
                'id' => $store->ulid,
                'name' => $store->name,
                'slug' => $store->slug,
                'status' => $store->status->value,
                'onboarding_status' => $store->onboarding_status->value,
                'onboarding_step' => $store->onboarding_status->step(),
            ] : null,
        ],
    ]);
})->middleware('auth:sanctum');
