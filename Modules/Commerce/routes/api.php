<?php

use Illuminate\Support\Facades\Route;
use Modules\Commerce\Http\Controllers\Admin\AdminAnalyticsController;
use Modules\Commerce\Http\Controllers\Admin\AdminCatalogLookupController;
use Modules\Commerce\Http\Controllers\Admin\AdminCategoryController;
use Modules\Commerce\Http\Controllers\Admin\AdminCollectionController;
use Modules\Commerce\Http\Controllers\Admin\AdminCustomerController;
use Modules\Commerce\Http\Controllers\Admin\AdminDashboardController;
use Modules\Commerce\Http\Controllers\Admin\AdminDiscountController;
use Modules\Commerce\Http\Controllers\Admin\AdminFinanceController;
use Modules\Commerce\Http\Controllers\Admin\AdminFulfillmentController;
use Modules\Commerce\Http\Controllers\Admin\AdminInventoryController;
use Modules\Commerce\Http\Controllers\Admin\AdminMarketingCampaignController;
use Modules\Commerce\Http\Controllers\Admin\AdminOrderController;
use Modules\Commerce\Http\Controllers\Admin\AdminProductController;
use Modules\Commerce\Http\Controllers\Admin\AdminProductCsvController;
use Modules\Commerce\Http\Controllers\Admin\AdminProductMediaController;
use Modules\Commerce\Http\Controllers\Admin\AdminReturnController;
use Modules\Commerce\Http\Controllers\Admin\AdminSettingsController;
use Modules\Commerce\Http\Controllers\Admin\AdminShippingMethodController;
use Modules\Commerce\Http\Controllers\Admin\AdminTaxRateController;
use Modules\Commerce\Http\Controllers\Admin\AdminStorefrontBuilderController;
use Modules\Commerce\Http\Controllers\Admin\AdminWorkspaceController;
use Modules\Commerce\Http\Controllers\Storefront\StorefrontCartController;
use Modules\Commerce\Http\Controllers\Storefront\StorefrontCatalogController;
use Modules\Commerce\Http\Controllers\Storefront\StorefrontCheckoutController;
use Modules\Commerce\Http\Controllers\Storefront\StorefrontEventController;

Route::prefix('storefront/v1')->middleware(['storefront.context', 'throttle:storefront'])->group(function () {
    Route::get('/runtime', [StorefrontCatalogController::class, 'runtime']);
    Route::get('/store', [StorefrontCatalogController::class, 'store']);
    Route::get('/products', [StorefrontCatalogController::class, 'index']);
    Route::get('/products/{slug}', [StorefrontCatalogController::class, 'show']);
    Route::get('/products/{slug}/media/{mediaUlid}', [StorefrontCatalogController::class, 'media']);
    Route::post('/events', [StorefrontEventController::class, 'store'])->middleware('throttle:storefront.events');

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
    Route::get('/search', [AdminWorkspaceController::class, 'search']);
    Route::get('/notifications', [AdminWorkspaceController::class, 'notifications']);
    Route::middleware('store.permission:themes.view')->group(function () {
        Route::get('/online-store', [AdminStorefrontBuilderController::class, 'overview']);
        Route::get('/online-store/themes', [AdminStorefrontBuilderController::class, 'themes']);
        Route::get('/online-store/themes/{themeUlid}/editor', [AdminStorefrontBuilderController::class, 'editor']);
        Route::get('/online-store/themes/{themeUlid}/preview-runtime', [AdminStorefrontBuilderController::class, 'previewRuntime']);
        Route::get('/store-builder/themes', [AdminStorefrontBuilderController::class, 'themes']);
        Route::get('/store-builder/themes/{themeUlid}/editor', [AdminStorefrontBuilderController::class, 'editor']);
    });
    Route::middleware('store.permission:themes.edit')->group(function () {
        Route::post('/online-store/themes/upload', [AdminStorefrontBuilderController::class, 'uploadTheme']);
        Route::post('/online-store/theme-packages/{packageUlid}/install', [AdminStorefrontBuilderController::class, 'installThemePackage']);
        Route::patch('/online-store/documents/{documentUlid}', [AdminStorefrontBuilderController::class, 'updateDocument']);
        Route::patch('/store-builder/documents/{documentUlid}', [AdminStorefrontBuilderController::class, 'updateDocument']);
    });
    Route::middleware('store.permission:themes.publish')->group(function () {
        Route::post('/online-store/themes/{themeUlid}/publish', [AdminStorefrontBuilderController::class, 'publish']);
        Route::post('/store-builder/themes/{themeUlid}/publish', [AdminStorefrontBuilderController::class, 'publish']);
    });
    Route::get('/analytics', [AdminAnalyticsController::class, 'show'])->middleware('store.permission:analytics.view');
    Route::middleware('store.permission:marketing.view')->group(function () {
        Route::get('/marketing/campaigns', [AdminMarketingCampaignController::class, 'index']);
    });
    Route::middleware('store.permission:marketing.manage')->group(function () {
        Route::post('/marketing/campaigns', [AdminMarketingCampaignController::class, 'store']);
        Route::patch('/marketing/campaigns/{ulid}', [AdminMarketingCampaignController::class, 'update']);
        Route::delete('/marketing/campaigns/{ulid}', [AdminMarketingCampaignController::class, 'destroy']);
    });

    Route::middleware('store.permission:orders.view')->group(function () {
        Route::get('/orders', [AdminOrderController::class, 'index']);
        Route::get('/orders/create-options', [AdminOrderController::class, 'createOptions']);
        Route::get('/orders/{ulid}', [AdminOrderController::class, 'show']);
    });
    Route::middleware('store.permission:orders.manage')->group(function () {
        Route::post('/orders', [AdminOrderController::class, 'store']);
        Route::post('/orders/{ulid}/cancel', [AdminOrderController::class, 'cancel']);
        Route::patch('/orders/{ulid}/fulfillment', [AdminOrderController::class, 'updateFulfillment']);
    });
    Route::post('/orders/{ulid}/payments/{paymentUlid}/refund', [AdminOrderController::class, 'refundPayment'])
        ->middleware('store.permission:orders.refund');

    Route::middleware('store.permission:orders.view')->group(function () {
        Route::get('/fulfillment', [AdminFulfillmentController::class, 'index']);
        Route::get('/returns', [AdminReturnController::class, 'index']);
    });
    Route::middleware('store.permission:orders.manage')->group(function () {
        Route::post('/orders/{orderUlid}/fulfillments', [AdminFulfillmentController::class, 'createFromOrder']);
        Route::post('/fulfillments/{fulfillmentUlid}/start', [AdminFulfillmentController::class, 'start']);
        Route::post('/fulfillments/{fulfillmentUlid}/scan', [AdminFulfillmentController::class, 'scan']);
        Route::post('/fulfillments/{fulfillmentUlid}/pack', [AdminFulfillmentController::class, 'pack']);
        Route::post('/fulfillments/{fulfillmentUlid}/shipments', [AdminFulfillmentController::class, 'createShipment']);
        Route::patch('/shipments/{shipmentUlid}/status', [AdminFulfillmentController::class, 'updateShipment']);
        Route::post('/returns', [AdminReturnController::class, 'store']);
        Route::post('/returns/{returnUlid}/approve', [AdminReturnController::class, 'approve']);
        Route::post('/returns/{returnUlid}/receive', [AdminReturnController::class, 'receive']);
        Route::post('/returns/{returnUlid}/inspect', [AdminReturnController::class, 'inspect']);
    });
    Route::post('/returns/{returnUlid}/refund', [AdminReturnController::class, 'refund'])
        ->middleware('store.permission:orders.refund');
    Route::get('/finance', [AdminFinanceController::class, 'show'])
        ->middleware('store.permission:analytics.view');

    Route::middleware('store.permission:customers.view')->group(function () {
        Route::get('/customers', [AdminCustomerController::class, 'index']);
        Route::get('/customers/{ulid}', [AdminCustomerController::class, 'show']);
    });
    Route::middleware('store.permission:customers.manage')->group(function () {
        Route::post('/customers', [AdminCustomerController::class, 'store']);
        Route::patch('/customers/{ulid}', [AdminCustomerController::class, 'update']);
    });

    Route::middleware('store.permission:discounts.view')->group(function () {
        Route::get('/discounts', [AdminDiscountController::class, 'index']);
        Route::get('/discounts/{ulid}', [AdminDiscountController::class, 'show']);
    });
    Route::middleware('store.permission:discounts.manage')->group(function () {
        Route::post('/discounts', [AdminDiscountController::class, 'store']);
        Route::patch('/discounts/{ulid}', [AdminDiscountController::class, 'update']);
        Route::delete('/discounts/{ulid}', [AdminDiscountController::class, 'destroy']);
    });

    Route::middleware('store.permission:settings.view')->group(function () {
        Route::get('/settings', [AdminSettingsController::class, 'show']);
        Route::get('/integrations', [AdminSettingsController::class, 'integrations']);
        Route::get('/settings/shipping-methods', [AdminShippingMethodController::class, 'index']);
        Route::get('/settings/tax-rates', [AdminTaxRateController::class, 'index']);
    });
    Route::middleware('store.permission:settings.manage')->group(function () {
        Route::patch('/settings/store', [AdminSettingsController::class, 'updateStore']);
        Route::post('/settings/domains', [AdminSettingsController::class, 'storeDomain']);
        Route::post('/settings/domains/{ulid}/verify', [AdminSettingsController::class, 'verifyDomain']);
        Route::post('/settings/domains/{ulid}/primary', [AdminSettingsController::class, 'makePrimaryDomain']);
        Route::delete('/settings/domains/{ulid}', [AdminSettingsController::class, 'destroyDomain']);
        Route::post('/settings/shipping-methods', [AdminShippingMethodController::class, 'store']);
        Route::patch('/settings/shipping-methods/{ulid}', [AdminShippingMethodController::class, 'update']);
        Route::delete('/settings/shipping-methods/{ulid}', [AdminShippingMethodController::class, 'destroy']);
        Route::post('/settings/tax-rates', [AdminTaxRateController::class, 'store']);
        Route::patch('/settings/tax-rates/{ulid}', [AdminTaxRateController::class, 'update']);
        Route::delete('/settings/tax-rates/{ulid}', [AdminTaxRateController::class, 'destroy']);
    });

    Route::middleware('store.permission:products.view')->group(function () {
        Route::get('/categories', [AdminCategoryController::class, 'index']);
        Route::get('/categories/{ulid}', [AdminCategoryController::class, 'show']);
        Route::get('/collections', [AdminCollectionController::class, 'index']);
        Route::get('/collections/{ulid}', [AdminCollectionController::class, 'show']);

        Route::get('/inventory', [AdminInventoryController::class, 'index']);
        Route::get('/products/export', [AdminProductCsvController::class, 'export']);
        Route::get('/products', [AdminProductController::class, 'index']);
        Route::get('/products/{ulid}', [AdminProductController::class, 'show']);
        Route::get('/products/{productUlid}/media/{mediaUlid}/file', [AdminProductMediaController::class, 'file']);
        Route::get('/catalog/organization', [AdminCatalogLookupController::class, 'index']);
    });

    Route::middleware('store.permission:products.manage')->group(function () {
        Route::post('/categories', [AdminCategoryController::class, 'store']);
        Route::patch('/categories/{ulid}', [AdminCategoryController::class, 'update']);
        Route::delete('/categories/{ulid}', [AdminCategoryController::class, 'destroy']);
        Route::post('/collections', [AdminCollectionController::class, 'store']);
        Route::patch('/collections/{ulid}', [AdminCollectionController::class, 'update']);
        Route::put('/collections/{ulid}/products', [AdminCollectionController::class, 'syncProducts']);
        Route::delete('/collections/{ulid}', [AdminCollectionController::class, 'destroy']);

        Route::post('/products/import', [AdminProductCsvController::class, 'import']);
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

    Route::middleware('store.permission:inventory.manage')->group(function () {
        Route::patch('/inventory/{inventoryItemUlid}/locations/{locationUlid}', [AdminInventoryController::class, 'adjust']);
    });
});
