<?php

namespace App\Core\Tenancy\Http\Middleware;

use App\Core\Tenancy\CurrentStore;
use App\Core\Tenancy\Scopes\StoreScope;
use Closure;
use Illuminate\Http\Request;
use Modules\Store\Enums\StoreStatus;
use Modules\Store\Models\Store;
use Modules\Store\Models\StoreDomain;
use Modules\Store\Support\ReservedStoreSlugs;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the store for a storefront request from its Host header —
 * either a verified custom domain (store_domains.domain) or the
 * {slug}.rivaify.com / .rivaify.test / .rivaify.localhost wildcard —
 * mirroring EnsureStoreContext but keyed by hostname instead of an
 * authenticated session, since storefront visitors are anonymous.
 */
class EnsureStorefrontStoreContext
{
    public function __construct(private readonly CurrentStore $currentStore) {}

    public function handle(Request $request, Closure $next): Response
    {
        $host = mb_strtolower((string) ($request->header('Host') ?? $request->getHost()));
        $host = str($host)->before(':')->toString();
        $store = $this->resolveStore($host);
        if ($store === null) {
            return response()->json(['message' => 'store_not_found'], 404);
        }

        $this->currentStore->set($store);

        return $next($request);
    }

    private function resolveStore(string $host): ?Store
    {
        $domain = StoreDomain::withoutGlobalScope(StoreScope::class)
            ->with('store')
            ->where('domain', $host)
            ->whereNotNull('verified_at')
            ->first();
        if ($domain?->store !== null && $domain->store->status === StoreStatus::Active) {
            return $domain->store;
        }

        foreach (['.rivaify.com', '.rivaify.test', '.rivaify.localhost'] as $suffix) {
            if (! str_ends_with($host, $suffix)) {
                continue;
            }

            $slug = str($host)->beforeLast($suffix)->toString();
            if ($slug === '' || str_contains($slug, '.') || ReservedStoreSlugs::has($slug)) {
                return null;
            }

            return Store::query()
                ->where('slug', $slug)
                ->where('status', StoreStatus::Active)
                ->first();
        }

        return null;
    }
}
