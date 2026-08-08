<?php

namespace Modules\Commerce\Models\Inventory;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Commerce\Models\Catalog\ProductVariant;

#[Fillable(['product_variant_id', 'is_tracked', 'allow_oversell'])]
class InventoryItem extends Model
{
    use BelongsToStore, HasFactory, HasUlid;

    protected $attributes = [
        'is_tracked' => true,
        'allow_oversell' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_tracked' => 'boolean',
            'allow_oversell' => 'boolean',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function levels(): HasMany
    {
        return $this->hasMany(InventoryLevel::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    public function reservations(): HasMany
    {
        return $this->hasMany(InventoryReservation::class);
    }
}