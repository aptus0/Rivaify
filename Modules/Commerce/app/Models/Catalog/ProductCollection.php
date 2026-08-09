<?php

namespace Modules\Commerce\Models\Catalog;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Modules\Commerce\Enums\Catalog\CollectionStatus;

#[Fillable(['name', 'slug', 'description', 'status', 'position'])]
class ProductCollection extends Model
{
    use BelongsToStore, HasFactory, HasUlid;

    protected $table = 'collections';

    protected $attributes = [
        'status' => 'draft',
        'position' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => CollectionStatus::class,
            'position' => 'integer',
        ];
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'collection_product', 'collection_id', 'product_id')
            ->withPivot(['store_id', 'position'])
            ->withTimestamps()
            ->orderByPivot('position');
    }
}
