<?php

namespace Modules\Ecosystem\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    protected string $name = 'Ecosystem';

    public function boot(): void
    {
        parent::boot();
    }

    public function map(): void
    {
        $this->mapApiRoutes();
        $this->mapWebhookRoutes();
    }

    /**
     * Stateless admin API (integration list, connect, disconnect, sync, health).
     */
    protected function mapApiRoutes(): void
    {
        Route::middleware('api')->prefix('api')->name('api.')->group(module_path($this->name, '/routes/api.php'));
    }

    /**
     * OAuth callback + inbound provider webhooks. Kept out of the `api`
     * group: the OAuth callback is a raw top-level browser redirect from
     * the provider (Meta), so it can't carry a Sanctum bearer token or
     * rely on the SPA's stateful-domain cookie check — store context is
     * resolved from a signed, one-time state token instead (see
     * OAuthStateStore), not from auth middleware.
     */
    protected function mapWebhookRoutes(): void
    {
        Route::middleware('web')->group(module_path($this->name, '/routes/webhooks.php'));
    }
}
