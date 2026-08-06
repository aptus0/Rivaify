<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Scoped to the public marketing site only (routes/web.php's rivaify.com
 * group) — the merchant dashboard and storefront stay on their existing
 * plain React Router SPAs, untouched by this middleware.
 */
class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
        ];
    }
}
