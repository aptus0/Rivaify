<?php

namespace Modules\Identity\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Identity\Events\UserRegistered;
use Modules\Identity\Listeners\LogUserRegisteredActivity;
use Modules\Identity\Listeners\SendEmailVerificationNotification;
use Modules\Identity\Listeners\SendWelcomeEmail;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event handler mappings for the application.
     *
     * @var array<string, array<int, string>>
     */
    protected $listen = [
        UserRegistered::class => [
            LogUserRegisteredActivity::class,
            SendEmailVerificationNotification::class,
            SendWelcomeEmail::class,
        ],
    ];

    /**
     * Indicates if events should be discovered.
     *
     * @var bool
     */
    protected static $shouldDiscoverEvents = false;
}
