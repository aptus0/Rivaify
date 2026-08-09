<?php

namespace Modules\Ecosystem\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\Ecosystem\Jobs\CheckIntegrationHealth;
use Nwidart\Modules\Support\ModuleServiceProvider;

class EcosystemServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Ecosystem';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'ecosystem';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        RouteServiceProvider::class,
    ];

    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->job(new CheckIntegrationHealth)->everyFifteenMinutes()->withoutOverlapping();
    }
}
