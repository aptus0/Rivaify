<?php

namespace Modules\Commerce\Models\Inventory;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'inventory_item_id', 'inventory_location_id', 'type', 'quantity_delta', 'quantity_before',
    'quantity_after', 'reason', 'reference_type', 'reference_id', 'created_by',
])]
class InventoryMovement extends Model
{
    use BelongsToStore, HasUlid;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'quantity_delta' => 'integer',
            'quantity_before' => 'integer',
            'quantity_after' => 'integer',
        ];
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InventoryLocation::class, 'inventory_location_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}