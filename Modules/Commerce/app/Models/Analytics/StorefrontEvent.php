<?php

namespace Modules\Commerce\Models\Analytics;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Commerce\Enums\Analytics\StorefrontEventType;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Models\Checkout\CheckoutSession;
use Modules\Commerce\Models\Order\Order;

#[Fillable([
    'event_type', 'session_hash', 'product_id', 'checkout_id', 'order_id', 'page_path',
    'source', 'utm_source', 'utm_medium', 'utm_campaign', 'referrer_host', 'occurred_at',
])]
class StorefrontEvent extends Model
{
    use BelongsToStore, HasUlid;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return [
            'event_type' => StorefrontEventType::class,
            'occurred_at' => 'datetime',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(CheckoutSession::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
