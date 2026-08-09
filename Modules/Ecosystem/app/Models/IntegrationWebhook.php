<?php

namespace Modules\Ecosystem\Models;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Modules\Ecosystem\Enums\IntegrationWebhookStatus;

#[Fillable([
    'store_id', 'integration_key', 'external_event_id', 'event_type', 'payload',
    'status', 'attempts', 'received_at', 'processed_at', 'last_error',
])]
class IntegrationWebhook extends Model
{
    use BelongsToStore, HasUlid;

    protected function casts(): array
    {
        return [
            'status' => IntegrationWebhookStatus::class,
            'payload' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
