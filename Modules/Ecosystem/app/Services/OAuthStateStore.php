<?php

namespace Modules\Ecosystem\Services;

use Illuminate\Support\Facades\Cache;
use Modules\Store\Models\Store;

/**
 * Carries the initiating store through an OAuth redirect round-trip
 * without relying on a session cookie. A raw top-level browser
 * navigation back from the provider (Meta) may arrive with no Referer
 * pointing at our own domain, so Sanctum's stateful-domain check can't be
 * trusted to authenticate it — this opaque, single-use, short-lived
 * token is the actual CSRF/identity guard for the callback instead.
 */
class OAuthStateStore
{
    public function mint(Store $store, string $integrationKey): string
    {
        $token = (string) str()->random(40);
        Cache::put(
            $this->cacheKey($token),
            ['store_id' => $store->id, 'integration_key' => $integrationKey],
            now()->addMinutes((int) config('ecosystem.oauth_state_ttl_minutes', 10)),
        );

        return $token;
    }

    /**
     * @return array{store_id: int, integration_key: string}|null
     */
    public function consume(string $token): ?array
    {
        return Cache::pull($this->cacheKey($token));
    }

    private function cacheKey(string $token): string
    {
        return "ecosystem:oauth_state:{$token}";
    }
}
