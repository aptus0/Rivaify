<?php

use Illuminate\Support\Facades\Route;
use Modules\Verification\Http\Controllers\Admin\VerificationReviewController;
use Modules\Verification\Http\Controllers\VerificationOnboardingController;

Route::middleware(['auth:sanctum', 'store.context'])->group(function () {
    Route::get('/store/verification-documents', [VerificationOnboardingController::class, 'index']);
    Route::post('/store/verification-documents', [VerificationOnboardingController::class, 'uploadDocument']);
    Route::post('/store/verification-request', [VerificationOnboardingController::class, 'submit']);
});

// Rivaify Admin — deliberately outside store.context (brief §11: admin
// review is cross-tenant, not "inside" any one merchant's store).
Route::middleware(['auth:sanctum', 'rivaify.admin'])->prefix('admin')->group(function () {
    Route::get('/verification-requests', [VerificationReviewController::class, 'index']);
    Route::get('/verification-requests/{verificationRequest}', [VerificationReviewController::class, 'show']);
    Route::post('/verification-requests/{verificationRequest}/approve', [VerificationReviewController::class, 'approve']);
    Route::post('/verification-requests/{verificationRequest}/reject', [VerificationReviewController::class, 'reject']);
});
