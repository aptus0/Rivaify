<?php

namespace Modules\Commerce\Models\Order;

use App\Core\Shared\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Store\Models\Store;

#[Fillable(['store_id', 'order_id', 'type', 'status', 'attempts', 'last_error', 'sent_at'])]
class OrderNotificationOutbox extends Model
{
    use HasUlid;

    protected $table = 'order_notification_outbox';

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}