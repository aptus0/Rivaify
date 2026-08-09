<?php

namespace Modules\Ecosystem\Services;

use Modules\Ecosystem\Models\IntegrationActivityLog;
use Modules\Ecosystem\Models\StoreIntegration;

class IntegrationActivityLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(StoreIntegration $integration, string $type, string $message, array $metadata = []): IntegrationActivityLog
    {
        return IntegrationActivityLog::query()->create([
            'store_id' => $integration->store_id,
            'integration_key' => $integration->integration_key,
            'type' => $type,
            'message' => $message,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
