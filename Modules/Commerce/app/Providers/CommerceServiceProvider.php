<?php

namespace Modules\Commerce\Providers;

use Nwidart\Modules\Support\ModuleServiceProvider;

class CommerceServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'Commerce';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'commerce';

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];
}
