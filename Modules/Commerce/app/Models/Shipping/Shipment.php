<?php

namespace Modules\Commerce\Models\Shipping;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Commerce\Enums\Shipping\ShipmentStatus;
use Modules\Commerce\Models\Fulfillment\Fulfillment;
use Modules\Commerce\Models\Order\Order;

#[Fillable([
    'order_id', 'fulfillment_id', 'provider', 'external_reference', 'provider_shipment_id',
    'tracking_number', 'tracking_url', 'status', 'service_code', 'package_weight',
    'package_dimensions', 'label_disk', 'label_path', 'shipped_at', 'delivered_at',
])]
class Shipment extends Model
{
    use BelongsToStore, HasFactory, HasUlid;

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'package_weight' => 'decimal:3',
            'package_dimensions' => 'array',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ShipmentEvent::class)->orderBy('occurred_at');
    }
}
