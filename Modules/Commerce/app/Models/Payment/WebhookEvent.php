<?php

namespace Modules\Commerce\Models\Payment;

use App\Core\Shared\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'provider', 'external_event_id', 'type', 'payload', 'status', 'received_at', 'processed_at',
    'attempts', 'last_error',
])]
class WebhookEvent extends Model
{
    use HasUlid;

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}