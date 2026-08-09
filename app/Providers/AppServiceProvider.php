<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('storefront', fn (Request $request) => Limit::perMinute(120)
            ->by($request->getHost().'|'.$request->ip()));

        RateLimiter::for('storefront.payment', fn (Request $request) => Limit::perMinute(10)
            ->by($request->getHost().'|'.$request->ip()));

        RateLimiter::for('payment.webhook', function (Request $request): array {
            $provider = mb_strtolower((string) $request->route('provider'));
            $eventReference = (string) ($request->input('merchant_oid')
                ?? $request->input('event_id')
                ?? $request->input('id')
                ?? 'unknown');

            return [
                // Provider callbacks must not share the customer-facing
                // 10/minute payment limiter: a busy platform receives many
                // legitimate callbacks from the same provider IP range.
                Limit::perMinute(1200)
                    ->by($request->getHost().'|'.$provider.'|'.$request->ip()),
                Limit::perMinute(30)
                    ->by('event|'.hash('sha256', $provider.'|'.$eventReference)),
            ];
        });

        RateLimiter::for('storefront.events', fn (Request $request) => Limit::perMinute(30)
            ->by($request->getHost().'|'.$request->ip()));
    }
}
