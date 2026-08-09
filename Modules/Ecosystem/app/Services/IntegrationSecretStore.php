<?php

namespace Modules\Ecosystem\Services;

use Modules\Ecosystem\Models\StoreIntegration;

/**
 * The one place credentials get written or read. Backed today by the
 * `encrypted:array` cast on store_integrations.credentials (Laravel
 * encryption / APP_KEY); a future move to a real Vault/KMS only touches
 * this class (brief §7).
 */
class IntegrationSecretStore
{
    /**
     * @param  array<string, mixed>  $credentials
     */
    public function store(StoreIntegration $integration, array $credentials): void
    {
        $integration->update(['credentials' => $credentials]);
    }

    /**
     * @return array<string, mixed>
     */
    public function retrieve(StoreIntegration $integration): array
    {
        return $integration->credentials ?? [];
    }

    public function get(StoreIntegration $integration, string $field): mixed
    {
        return $this->retrieve($integration)[$field] ?? null;
    }

    public function forget(StoreIntegration $integration): void
    {
        $integration->update(['credentials' => null]);
    }
}
