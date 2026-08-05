<?php

use Illuminate\Support\Facades\Route;
use Modules\Store\Http\Controllers\StoreOnboardingController;

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/stores', [StoreOnboardingController::class, 'store']);

    Route::middleware('store.context')->group(function () {
        Route::get('/store', [StoreOnboardingController::class, 'current']);
    });
});
