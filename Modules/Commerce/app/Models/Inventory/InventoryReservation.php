<?php

namespace Modules\Commerce\Models\Inventory;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Commerce\Enums\Inventory\InventoryReservationStatus;
use Modules\Commerce\Models\Checkout\CheckoutSession;

#[Fillable([
    'inventory_item_id', 'location_id', 'checkout_id', 'quantity', 'status', 'expires_at',
    'released_at', 'committed_at',
])]
class InventoryReservation extends Model
{
    use BelongsToStore, HasFactory, HasUlid;

    protected $attributes = [
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'status' => InventoryReservationStatus::class,
            'quantity' => 'integer',
            'expires_at' => 'datetime',
            'released_at' => 'datetime',
            'committed_at' => 'datetime',
        ];
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'location_id');
    }

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(CheckoutSession::class, 'checkout_id');
    }
}
