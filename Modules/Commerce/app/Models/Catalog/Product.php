<?php

namespace Modules\Commerce\Models\Catalog;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Commerce\Enums\Catalog\ProductStatus;
use Modules\Commerce\Enums\Catalog\ProductType;

#[Fillable([
    'title', 'slug', 'description', 'category_id', 'brand_id', 'product_type', 'status', 'vendor',
    'is_taxable', 'requires_shipping', 'published_at', 'meta_title', 'meta_description', 'package_width',
    'package_height', 'package_length', 'package_dimension_unit', 'created_by', 'updated_by',
])]
class Product extends Model
{
    use BelongsToStore, HasFactory, HasUlid, SoftDeletes;

    protected $attributes = [
        'product_type' => 'physical',
        'status' => 'draft',
        'is_taxable' => true,
        'requires_shipping' => true,
    ];

    protected function casts(): array
    {
        return [
            'product_type' => ProductType::class,
            'status' => ProductStatus::class,
            'is_taxable' => 'boolean',
            'requires_shipping' => 'boolean',
            'package_width' => 'decimal:2',
            'package_height' => 'decimal:2',
            'package_length' => 'decimal:2',
            'published_at' => 'datetime',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function options(): HasMany
    {
        return $this->hasMany(ProductOption::class)->orderBy('position');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('position');
    }

    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class)->orderBy('position');
    }

    public function tags(): HasMany
    {
        return $this->hasMany(ProductTag::class)->orderBy('name');
    }

    public function collections(): BelongsToMany
    {
        return $this->belongsToMany(ProductCollection::class, 'collection_product', 'product_id', 'collection_id')
            ->withPivot(['store_id', 'position'])
            ->withTimestamps()
            ->orderByPivot('position');
    }
}
