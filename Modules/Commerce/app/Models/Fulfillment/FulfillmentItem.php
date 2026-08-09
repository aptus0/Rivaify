<?php

namespace Modules\Commerce\Models\Fulfillment;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Commerce\Enums\Fulfillment\FulfillmentItemStatus;
use Modules\Commerce\Models\Order\OrderItem;

#[Fillable(['fulfillment_id', 'order_item_id', 'quantity', 'picked_quantity', 'status', 'picked_at'])]
class FulfillmentItem extends Model
{
    use BelongsToStore, HasFactory, HasUlid;

    protected $attributes = [
        'status' => 'pending',
        'picked_quantity' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => FulfillmentItemStatus::class,
            'quantity' => 'integer',
            'picked_quantity' => 'integer',
            'picked_at' => 'datetime',
        ];
    }

    public function fulfillment(): BelongsTo
    {
        return $this->belongsTo(Fulfillment::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
