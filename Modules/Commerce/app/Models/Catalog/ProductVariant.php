<?php

namespace Modules\Commerce\Models\Catalog;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Commerce\Enums\Catalog\ProductStatus;

#[Fillable([
    'product_id', 'title', 'sku', 'barcode', 'price', 'compare_at_price',
    'cost_price', 'weight', 'weight_unit', 'requires_shipping', 'is_taxable',
    'position', 'status',
])]
class ProductVariant extends Model
{
    use BelongsToStore, HasFactory, HasUlid, SoftDeletes;

    protected $attributes = [
        'title' => 'Default',
        'price' => 0,
        'weight_unit' => 'kg',
        'requires_shipping' => true,
        'is_taxable' => true,
        'position' => 0,
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'weight' => 'decimal:3',
            'requires_shipping' => 'boolean',
            'is_taxable' => 'boolean',
            'status' => ProductStatus::class,
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The option/value pairs that make up this variant's combination, e.g.
     * Color -> Black, Size -> 42. Empty for a "no variants" product's
     * auto-created Default variant (brief §8).
     */
    public function variantValues(): HasMany
    {
        return $this->hasMany(ProductVariantValue::class);
    }
}
