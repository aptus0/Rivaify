<?php

namespace Modules\Commerce\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Commerce\Events\Catalog\CategoryCreated;
use Modules\Commerce\Events\Catalog\ProductCreated;
use Modules\Commerce\Events\Catalog\ProductUpdated;
use Modules\Commerce\Events\Customer\CustomerCreated;
use Modules\Commerce\Events\Inventory\InventoryAdjusted;
use Modules\Commerce\Events\Inventory\InventoryReservationReleased;
use Modules\Commerce\Events\Inventory\InventoryReserved;
use Modules\Commerce\Events\Order\OrderCancelled;
use Modules\Commerce\Events\Order\OrderPlaced;
use Modules\Commerce\Events\Payment\PaymentFailed;
use Modules\Commerce\Events\Payment\PaymentRefunded;
use Modules\Commerce\Events\Payment\PaymentSucceeded;
use Modules\Commerce\Listeners\InvalidateMerchantDashboardCache;
use Modules\Commerce\Listeners\LogCategoryCreatedActivity;
use Modules\Commerce\Listeners\LogProductCreatedActivity;
use Modules\Commerce\Listeners\LogProductUpdatedActivity;
use Modules\Commerce\Listeners\ProvisionStoreFulfillmentDefaults;
use Modules\Commerce\Listeners\SendCustomerOrderCancelledNotification;
use Modules\Commerce\Listeners\SendCustomerOrderConfirmation;
use Modules\Commerce\Listeners\SendCustomerRefundConfirmation;
use Modules\Commerce\Listeners\SendMerchantNewOrderNotification;
use Modules\Commerce\Listeners\UpdateCustomerOrderStats;
use Modules\Store\Events\StoreCreated;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        StoreCreated::class => [
            ProvisionStoreFulfillmentDefaults::class,
        ],
        ProductCreated::class => [
            LogProductCreatedActivity::class,
        ],
        ProductUpdated::class => [
            LogProductUpdatedActivity::class,
        ],
        CategoryCreated::class => [
            LogCategoryCreatedActivity::class,
        ],
        OrderPlaced::class => [
            UpdateCustomerOrderStats::class,
            SendCustomerOrderConfirmation::class,
            SendMerchantNewOrderNotification::class,
            InvalidateMerchantDashboardCache::class,
        ],
        OrderCancelled::class => [
            SendCustomerOrderCancelledNotification::class,
            InvalidateMerchantDashboardCache::class,
        ],
        PaymentSucceeded::class => [
            InvalidateMerchantDashboardCache::class,
        ],
        PaymentFailed::class => [
            InvalidateMerchantDashboardCache::class,
        ],
        PaymentRefunded::class => [
            SendCustomerRefundConfirmation::class,
            InvalidateMerchantDashboardCache::class,
        ],
        CustomerCreated::class => [
            InvalidateMerchantDashboardCache::class,
        ],
        InventoryAdjusted::class => [
            InvalidateMerchantDashboardCache::class,
        ],
        InventoryReserved::class => [
            InvalidateMerchantDashboardCache::class,
        ],
        InventoryReservationReleased::class => [
            InvalidateMerchantDashboardCache::class,
        ],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = false;
}
