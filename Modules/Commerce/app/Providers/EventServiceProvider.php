<?php

namespace Modules\Commerce\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Commerce\Events\Catalog\CategoryCreated;
use Modules\Commerce\Events\Catalog\ProductCreated;
use Modules\Commerce\Events\Catalog\ProductUpdated;
use Modules\Commerce\Events\Order\OrderPlaced;
use Modules\Commerce\Events\Order\OrderCancelled;
use Modules\Commerce\Events\Payment\PaymentRefunded;
use Modules\Commerce\Listeners\LogCategoryCreatedActivity;
use Modules\Commerce\Listeners\LogProductCreatedActivity;
use Modules\Commerce\Listeners\LogProductUpdatedActivity;
use Modules\Commerce\Listeners\SendCustomerOrderConfirmation;
use Modules\Commerce\Listeners\SendCustomerOrderCancelledNotification;
use Modules\Commerce\Listeners\SendCustomerRefundConfirmation;
use Modules\Commerce\Listeners\SendMerchantNewOrderNotification;
use Modules\Commerce\Listeners\UpdateCustomerOrderStats;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
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
        ],
        OrderCancelled::class => [
            SendCustomerOrderCancelledNotification::class,
        ],
        PaymentRefunded::class => [
            SendCustomerRefundConfirmation::class,
        ],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = false;
}
