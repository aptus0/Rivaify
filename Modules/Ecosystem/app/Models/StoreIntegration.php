<?php

namespace Modules\Ecosystem\Models;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Ecosystem\Enums\IntegrationStatus;

#[Fillable([
    'store_id', 'integration_key', 'status', 'configuration', 'credentials',
    'connected_at', 'disconnected_at', 'last_sync_at', 'last_health_check_at',
])]
class StoreIntegration extends Model
{
    use BelongsToStore, HasUlid;

    protected function casts(): array
    {
        return [
            'status' => IntegrationStatus::class,
            'configuration' => 'array',
            'credentials' => 'encrypted:array',
            'connected_at' => 'datetime',
            'disconnected_at' => 'datetime',
            'last_sync_at' => 'datetime',
            'last_health_check_at' => 'datetime',
        ];
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(IntegrationActivityLog::class, 'integration_key', 'integration_key')->latest('created_at');
    }

    /**
     * A masked, display-safe view of one credential value — the frontend
     * never receives the real secret (brief §7: "Frontend'e hiçbir zaman
     * secret/token/refresh_token dönülmez").
     */
    public function maskedCredential(string $field): ?string
    {
        $value = $this->credentials[$field] ?? null;
        if (! is_string($value) || $value === '') {
            return null;
        }

        return str_repeat('•', 12).mb_substr($value, -4);
    }
}
