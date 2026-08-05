<?php

use Illuminate\Support\Facades\Route;
use Modules\Merchant\Http\Controllers\MerchantOnboardingController;

Route::middleware(['auth:sanctum', 'store.context'])->group(function () {
    Route::post('/store/business-profile', [MerchantOnboardingController::class, 'submitBusinessProfile']);
    Route::post('/store/tax-profile', [MerchantOnboardingController::class, 'submitTaxProfile']);
});
