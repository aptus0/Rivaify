<?php

namespace Modules\Commerce\Models\Shipping;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Commerce\Enums\Shipping\ShipmentStatus;

#[Fillable([
    'shipment_id', 'provider_event_id', 'provider_status', 'normalized_status',
    'location', 'message', 'occurred_at', 'payload_reference', 'payload',
])]
class ShipmentEvent extends Model
{
    use BelongsToStore, HasFactory, HasUlid;

    protected function casts(): array
    {
        return [
            'normalized_status' => ShipmentStatus::class,
            'occurred_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(Shipment::class);
    }
}
