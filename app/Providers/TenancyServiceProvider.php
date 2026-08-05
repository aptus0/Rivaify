<?php

namespace App\Providers;

use App\Core\Tenancy\CurrentStore;
use Illuminate\Support\ServiceProvider;

class TenancyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CurrentStore::class);
    }
}
