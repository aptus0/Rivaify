<?php

namespace Modules\Commerce\Models\Inventory;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['inventory_item_id', 'inventory_location_id', 'available_quantity', 'reserved_quantity', 'incoming_quantity'])]
class InventoryLevel extends Model
{
    use BelongsToStore, HasFactory, HasUlid;

    protected function casts(): array
    {
        return [
            'available_quantity' => 'integer',
            'reserved_quantity' => 'integer',
            'incoming_quantity' => 'integer',
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

    public function sellableQuantity(): int
    {
        return $this->available_quantity - $this->reserved_quantity;
    }
}