<?php

namespace Modules\Ecosystem\Contracts;

use Modules\Ecosystem\DTOs\HealthCheckResult;
use Modules\Ecosystem\Models\StoreIntegration;

/**
 * The operations every integration connector supports regardless of
 * domain (payment, shipping, social...) — core services depend only on
 * this (and its domain-specific children below), never on a concrete
 * provider class (brief §5).
 */
interface IntegrationConnector
{
    /**
     * Best-effort revoke on the provider side, then the caller marks the
     * store_integrations row disconnected regardless of whether revoke
     * succeeded — a merchant's "disconnect" must never get stuck because
     * the remote API call failed.
     */
    public function disconnect(StoreIntegration $integration): void;

    public function healthCheck(StoreIntegration $integration): HealthCheckResult;

    public function refreshCredentials(StoreIntegration $integration): void;
}
