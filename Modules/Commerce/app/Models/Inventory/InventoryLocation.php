<?php

namespace Modules\Commerce\Models\Inventory;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'code', 'type', 'address_line_1', 'province', 'district', 'phone', 'is_active',
    'fulfillment_enabled', 'inventory_enabled',
])]
class InventoryLocation extends Model
{
    use BelongsToStore, HasFactory, HasUlid;

    protected $attributes = [
        'type' => 'warehouse',
        'is_active' => true,
        'fulfillment_enabled' => true,
        'inventory_enabled' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'fulfillment_enabled' => 'boolean',
            'inventory_enabled' => 'boolean',
        ];
    }

    public function levels(): HasMany
    {
        return $this->hasMany(InventoryLevel::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }
}