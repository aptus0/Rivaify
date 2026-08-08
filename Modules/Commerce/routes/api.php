<?php

use Illuminate\Support\Facades\Route;
use Modules\Commerce\Http\Controllers\Admin\AdminCustomerController;
use Modules\Commerce\Http\Controllers\Admin\AdminDashboardController;
use Modules\Commerce\Http\Controllers\Admin\AdminDiscountController;
use Modules\Commerce\Http\Controllers\Admin\AdminOrderController;
use Modules\Commerce\Http\Controllers\Admin\AdminCatalogLookupController;
use Modules\Commerce\Http\Controllers\Admin\AdminProductController;
use Modules\Commerce\Http\Controllers\Admin\AdminProductMediaController;
use Modules\Commerce\Http\Controllers\Storefront\StorefrontCartController;
use Modules\Commerce\Http\Controllers\Storefront\StorefrontCatalogController;
use Modules\Commerce\Http\Controllers\Storefront\StorefrontCheckoutController;

Route::prefix('storefront/v1')->middleware(['storefront.context', 'throttle:storefront'])->group(function () {
	Route::get('/store', [StorefrontCatalogController::class, 'store']);
	Route::get('/products', [StorefrontCatalogController::class, 'index']);
	Route::get('/products/{slug}', [StorefrontCatalogController::class, 'show']);

	Route::get('/cart', [StorefrontCartController::class, 'show']);
	Route::post('/cart', [StorefrontCartController::class, 'create']);
	Route::post('/cart/items', [StorefrontCartController::class, 'addItem']);
	Route::patch('/cart/items/{itemUlid}', [StorefrontCartController::class, 'updateItem']);
	Route::delete('/cart/items/{itemUlid}', [StorefrontCartController::class, 'removeItem']);
	Route::delete('/cart', [StorefrontCartController::class, 'clear']);

	Route::post('/checkout', [StorefrontCheckoutController::class, 'start']);
	Route::get('/checkouts/{token}', [StorefrontCheckoutController::class, 'show']);
	Route::patch('/checkouts/{token}/customer', [StorefrontCheckoutController::class, 'updateCustomer']);
	Route::patch('/checkouts/{token}/address', [StorefrontCheckoutController::class, 'updateAddresses']);
	Route::get('/checkouts/{token}/shipping-methods', [StorefrontCheckoutController::class, 'shippingQuotes']);
	Route::post('/checkouts/{token}/shipping', [StorefrontCheckoutController::class, 'selectShipping']);
	Route::post('/checkouts/{token}/discount', [StorefrontCheckoutController::class, 'applyDiscount']);
	Route::post('/checkouts/{token}/tax', [StorefrontCheckoutController::class, 'applyTax']);
	Route::post('/checkouts/{token}/payment', [StorefrontCheckoutController::class, 'pay'])->middleware('throttle:storefront.payment');
	Route::get('/checkouts/{token}/confirmation', [StorefrontCheckoutController::class, 'confirmation']);
});

Route::prefix('v1')->middleware(['auth:sanctum', 'store.context'])->group(function () {
	Route::get('/dashboard', [AdminDashboardController::class, 'show']);

	Route::get('/orders', [AdminOrderController::class, 'index']);
	Route::get('/orders/{ulid}', [AdminOrderController::class, 'show']);
	Route::post('/orders/{ulid}/cancel', [AdminOrderController::class, 'cancel']);
	Route::post('/orders/{ulid}/payments/{paymentUlid}/refund', [AdminOrderController::class, 'refundPayment']);

	Route::get('/customers', [AdminCustomerController::class, 'index']);
	Route::get('/customers/{ulid}', [AdminCustomerController::class, 'show']);

	Route::get('/discounts', [AdminDiscountController::class, 'index']);
	Route::post('/discounts', [AdminDiscountController::class, 'store']);
	Route::patch('/discounts/{ulid}', [AdminDiscountController::class, 'update']);

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
