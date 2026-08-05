<?php

namespace App\Core\Tenancy;

use App\Core\Tenancy\Exceptions\StoreContextMissingException;
use Modules\Store\Models\Store;

/**
 * The single source of truth for "which store is this request operating
 * on". Bound as a singleton in TenancyServiceProvider — one instance per
 * request (standard PHP-FPM lifecycle, container is rebuilt every request).
 *
 * Resolved by EnsureStoreContext middleware from the session, then every
 * BelongsToStore model automatically scopes its queries against whatever
 * is set here. Nothing else should read `session('current_store_id')`
 * directly — go through this class so there is exactly one place that
 * defines "current store".
 */
class CurrentStore
{
    private ?Store $store = null;

    public function set(Store $store): void
    {
        $this->store = $store;
    }

    public function clear(): void
    {
        $this->store = null;
    }

    public function has(): bool
    {
        return $this->store !== null;
    }

    public function store(): Store
    {
        if ($this->store === null) {
            throw new StoreContextMissingException(
                'No store context is bound. Route must run through the store.context middleware, '.
                'or the query must explicitly opt out via withoutGlobalScope(StoreScope::class).'
            );
        }

        return $this->store;
    }

    public function id(): int
    {
        return $this->store()->id;
    }
}
