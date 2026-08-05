<?php

use Illuminate\Support\Facades\Route;
use Modules\Commerce\Http\Controllers\CommerceController;

Route::middleware(['auth:sanctum'])->prefix('v1')->group(function () {
    Route::apiResource('commerces', CommerceController::class)->names('commerce');
});
