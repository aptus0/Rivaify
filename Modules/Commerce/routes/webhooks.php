<?php

use Illuminate\Support\Facades\Route;
use Modules\Commerce\Http\Controllers\PaymentWebhookController;

Route::post('/webhooks/payments/{provider}', [PaymentWebhookController::class, 'receive'])
    ->middleware('throttle:storefront.payment');