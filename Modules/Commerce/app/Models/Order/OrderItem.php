<?php

namespace Modules\Commerce\Models\Order;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Models\Catalog\ProductVariant;

#[Fillable([
    'order_id', 'product_id', 'variant_id', 'product_title', 'variant_title', 'sku', 'quantity',
    'unit_price', 'discount_total', 'tax_total', 'line_total', 'metadata',
])]
class OrderItem extends Model
{
    use BelongsToStore, HasUlid;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'line_total' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}