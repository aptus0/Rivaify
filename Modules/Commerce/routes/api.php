<?php

use Illuminate\Support\Facades\Route;
use Modules\Commerce\Http\Controllers\Admin\AdminCatalogLookupController;
use Modules\Commerce\Http\Controllers\Admin\AdminProductController;
use Modules\Commerce\Http\Controllers\Admin\AdminProductMediaController;

Route::prefix('v1')->middleware(['auth:sanctum', 'store.context'])->group(function () {
	Route::middleware('store.permission:products.view')->group(function () {
		Route::get('/products', [AdminProductController::class, 'index']);
		Route::get('/products/{ulid}', [AdminProductController::class, 'show']);
		Route::get('/products/{productUlid}/media/{mediaUlid}/file', [AdminProductMediaController::class, 'file']);
		Route::get('/catalog/organization', [AdminCatalogLookupController::class, 'index']);
	});

	Route::middleware('store.permission:products.manage')->group(function () {
		Route::post('/products', [AdminProductController::class, 'store']);
		Route::patch('/products/{ulid}', [AdminProductController::class, 'update']);
		Route::post('/products/{ulid}/duplicate', [AdminProductController::class, 'duplicate']);
		Route::post('/products/bulk', [AdminProductController::class, 'bulk']);
		Route::post('/products/{productUlid}/media', [AdminProductMediaController::class, 'store']);
		Route::patch('/products/{productUlid}/media/{mediaUlid}', [AdminProductMediaController::class, 'update']);
		Route::post('/products/{productUlid}/media/reorder', [AdminProductMediaController::class, 'reorder']);
		Route::delete('/products/{productUlid}/media/{mediaUlid}', [AdminProductMediaController::class, 'destroy']);
		Route::post('/catalog/categories', [AdminCatalogLookupController::class, 'storeCategory']);
		Route::post('/catalog/brands', [AdminCatalogLookupController::class, 'storeBrand']);
	});
});

Route::prefix('v1/storefront')->group(function () {
    Route::post('/checkout/initialize', [\Modules\Commerce\Http\Controllers\CheckoutController::class, 'initialize']);
    Route::post('/checkout/callback', [\Modules\Commerce\Http\Controllers\CheckoutController::class, 'callback']);
});
