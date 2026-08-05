<?php

namespace Modules\Commerce\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Commerce\Events\Catalog\ProductCreated;
use Modules\Commerce\Listeners\LogProductCreatedActivity;

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
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = false;
}
