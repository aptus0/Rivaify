<?php

namespace Modules\Store\Support;

/**
 * Slugs that must never resolve to a merchant store because they'd collide
 * with a system-level *.rivaify.com host (app., api., admin., ...) or a
 * predictable future one (help., status., ...). Enforced both at
 * store-creation time (CreateStore) and defensively at storefront
 * resolution time (EnsureStorefrontStoreContext) — the two run in separate
 * requests, so resolution can't assume creation already blocked everything
 * (e.g. rows inserted before this list existed, or via tinker/seeders).
 */
class ReservedStoreSlugs
{
    private const RESERVED = [
        'www', 'app', 'api', 'admin', 'cdn', 'assets', 'static',
        'mail', 'smtp', 'docs', 'developer', 'developers',
        'help', 'support', 'status', 'store', 'shop',
        'auth', 'login', 'register', 'billing', 'payments',
        'checkout', 'webhooks', 'internal', 'staging', 'dev', 'test',
    ];

    public static function has(string $slug): bool
    {
        return in_array($slug, self::RESERVED, true);
    }
}
