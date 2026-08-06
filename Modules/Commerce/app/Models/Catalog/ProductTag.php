<?php

namespace Modules\Commerce\Models\Catalog;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'name'])]
class ProductTag extends Model
{
    use BelongsToStore, HasFactory, HasUlid;

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}