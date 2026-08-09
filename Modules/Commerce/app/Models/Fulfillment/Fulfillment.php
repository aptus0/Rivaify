<?php

namespace Modules\Commerce\Models\Fulfillment;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Commerce\Enums\Fulfillment\FulfillmentStatus;
use Modules\Commerce\Models\Inventory\InventoryLocation;
use Modules\Commerce\Models\Order\Order;
use Modules\Commerce\Models\Shipping\Shipment;

#[Fillable([
    'order_id', 'location_id', 'status', 'assigned_to', 'started_at', 'picked_at',
    'packed_at', 'fulfilled_at', 'cancelled_at', 'package',
])]
class Fulfillment extends Model
{
    use BelongsToStore, HasFactory, HasUlid;

    protected $attributes = [
        'status' => 'unfulfilled',
    ];

    protected function casts(): array
    {
        return [
            'status' => FulfillmentStatus::class,
            'started_at' => 'datetime',
            'picked_at' => 'datetime',
            'packed_at' => 'datetime',
            'fulfilled_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'package' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function items(): HasMany
    {
        return $this->hasMany(FulfillmentItem::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }
}
