<?php

namespace Modules\Commerce\Models\Returns;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Commerce\Models\Order\OrderItem;

#[Fillable([
    'return_id', 'order_item_id', 'quantity', 'reason_code', 'condition', 'resolution', 'restock',
])]
class ReturnItem extends Model
{
    use BelongsToStore, HasFactory, HasUlid;

    protected $attributes = [
        'reason_code' => 'other',
        'resolution' => 'refund',
        'restock' => false,
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'restock' => 'boolean',
        ];
    }

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class, 'return_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }
}
