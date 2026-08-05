<?php

namespace Modules\Verification\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Verification\Events\VerificationRejected;
use Modules\Verification\Events\VerificationSubmitted;
use Modules\Verification\Listeners\LogVerificationSubmittedActivity;
use Modules\Verification\Listeners\SendVerificationRejectedNotification;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        VerificationSubmitted::class => [
            LogVerificationSubmittedActivity::class,
        ],
        VerificationRejected::class => [
            SendVerificationRejectedNotification::class,
        ],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = false;
}
