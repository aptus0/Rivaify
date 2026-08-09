<?php

namespace Modules\Ecosystem\Services;

use App\Core\Tenancy\CurrentStore;
use App\Core\Tenancy\Scopes\StoreScope;
use Illuminate\Support\Collection;
use Modules\Ecosystem\Contracts\IntegrationConnector;
use Modules\Ecosystem\Contracts\OAuthConnector;
use Modules\Ecosystem\DTOs\HealthCheckResult;
use Modules\Ecosystem\Enums\IntegrationStatus;
use Modules\Ecosystem\Exceptions\IntegrationNotAvailableException;
use Modules\Ecosystem\Exceptions\InvalidOAuthStateException;
use Modules\Ecosystem\Models\StoreIntegration;
use Modules\Ecosystem\Registry\IntegrationDefinition;
use Modules\Ecosystem\Registry\IntegrationRegistry;
use Modules\Store\Models\Store;

class IntegrationManager
{
    public function __construct(
        private readonly CurrentStore $currentStore,
        private readonly OAuthStateStore $states,
        private readonly IntegrationSecretStore $secrets,
        private readonly IntegrationActivityLogger $activity,
    ) {}

    /**
     * @return Collection<int, StoreIntegration>
     */
    public function forStore(Store $store): Collection
    {
        return StoreIntegration::query()->where('store_id', $store->id)->get()->keyBy('integration_key');
    }

    public function findOrCreate(Store $store, string $integrationKey): StoreIntegration
    {
        return StoreIntegration::query()->firstOrCreate(
            ['store_id' => $store->id, 'integration_key' => $integrationKey],
            ['status' => IntegrationStatus::Pending],
        );
    }

    public function startOAuth(Store $store, string $integrationKey): string
    {
        $connector = $this->connectorFor($integrationKey);
        if (! $connector instanceof OAuthConnector) {
            throw new \LogicException("Integration [{$integrationKey}] does not use an OAuth connect flow.");
        }
        $state = $this->states->mint($store, $integrationKey);

        return $connector->authorizationUrl($state);
    }

    public function completeOAuth(string $state, string $code): StoreIntegration
    {
        $context = $this->states->consume($state);
        if ($context === null) {
            throw new InvalidOAuthStateException('OAuth state is missing, already used, or expired.');
        }
        $store = Store::query()->findOrFail($context['store_id']);
        $integrationKey = $context['integration_key'];
        $connector = $this->connectorFor($integrationKey);
        if (! $connector instanceof OAuthConnector) {
            throw new \LogicException("Integration [{$integrationKey}] does not use an OAuth connect flow.");
        }

        $this->currentStore->set($store);
        try {
            $token = $connector->handleCallback($code);
            $integration = $this->findOrCreate($store, $integrationKey);
            $this->secrets->store($integration, [
                'access_token' => $token->accessToken,
                'refresh_token' => $token->refreshToken,
                'expires_at' => $token->expiresAt?->toIso8601String(),
            ]);
            $integration->update([
                'status' => IntegrationStatus::Connected,
                'configuration' => array_merge($integration->configuration ?? [], [
                    'external_account_id' => $token->externalAccountId,
                    'external_account_name' => $token->externalAccountName,
                ]),
                'connected_at' => now(),
                'disconnected_at' => null,
            ]);
            $this->activity->record($integration, 'connected', "{$integrationKey} bağlantısı kuruldu.", [
                'external_account_name' => $token->externalAccountName,
            ]);

            return $integration->refresh();
        } finally {
            $this->currentStore->clear();
        }
    }

    public function disconnect(Store $store, string $integrationKey): StoreIntegration
    {
        $integration = $this->findOrCreate($store, $integrationKey);
        $connector = $this->connectorFor($integrationKey);

        $this->currentStore->set($store);
        try {
            try {
                $connector->disconnect($integration);
            } catch (\Throwable $exception) {
                // Best-effort revoke — the merchant's disconnect must not get
                // stuck because the provider's API call failed.
                report($exception);
            }
            $integration->update([
                'status' => IntegrationStatus::Disconnected,
                'disconnected_at' => now(),
            ]);
            $this->secrets->forget($integration);
            $this->activity->record($integration, 'disconnected', "{$integrationKey} bağlantısı kesildi.");

            return $integration->refresh();
        } finally {
            $this->currentStore->clear();
        }
    }

    public function checkHealth(StoreIntegration $integration): HealthCheckResult
    {
        $connector = $this->connectorFor($integration->integration_key);
        $store = Store::withoutGlobalScope(StoreScope::class)->findOrFail($integration->store_id);

        $this->currentStore->set($store);
        try {
            $result = $connector->healthCheck($integration);
            $newStatus = $result->healthy ? IntegrationStatus::Connected : IntegrationStatus::AttentionRequired;
            if ($integration->status !== $newStatus) {
                $this->activity->record(
                    $integration,
                    $result->healthy ? 'health_recovered' : 'health_attention_required',
                    $result->message,
                );
            }
            $integration->update([
                'status' => $newStatus,
                'last_health_check_at' => now(),
            ]);

            return $result;
        } finally {
            $this->currentStore->clear();
        }
    }

    public function connectorFor(string $integrationKey): IntegrationConnector
    {
        $definition = IntegrationRegistry::find($integrationKey);
        if ($definition === null || $definition->connectorClass === null) {
            throw new IntegrationNotAvailableException("Integration [{$integrationKey}] is not available.");
        }

        /** @var IntegrationConnector $connector */
        $connector = app($definition->connectorClass);

        return $connector;
    }

    public function definitionOrFail(string $integrationKey): IntegrationDefinition
    {
        return IntegrationRegistry::find($integrationKey)
            ?? throw new IntegrationNotAvailableException("Integration [{$integrationKey}] does not exist.");
    }
}
