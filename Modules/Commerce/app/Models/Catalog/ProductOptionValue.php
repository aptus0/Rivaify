<?php

namespace Modules\Commerce\Models\Catalog;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_option_id', 'value', 'position'])]
class ProductOptionValue extends Model
{
    use BelongsToStore, HasFactory, HasUlid;

    public function option(): BelongsTo
    {
        return $this->belongsTo(ProductOption::class, 'product_option_id');
    }
}
