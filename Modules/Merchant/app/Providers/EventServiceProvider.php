<?php

namespace Modules\Merchant\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Merchant\Events\MerchantApproved;
use Modules\Merchant\Listeners\ActivateStore;
use Modules\Merchant\Listeners\LogMerchantApprovedActivity;
use Modules\Merchant\Listeners\SendMerchantApprovedNotification;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        MerchantApproved::class => [
            // Order matters: activate the store synchronously first, so
            // by the time the (queued) notification/log listeners run,
            // the store is already in its final state.
            ActivateStore::class,
            SendMerchantApprovedNotification::class,
            LogMerchantApprovedActivity::class,
        ],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = false;
}
