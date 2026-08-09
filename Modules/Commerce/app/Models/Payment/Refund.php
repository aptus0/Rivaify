<?php

namespace Modules\Commerce\Models\Payment;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Commerce\Enums\Payment\RefundStatus;
use Modules\Commerce\Models\Order\Order;
use Modules\Commerce\Models\Returns\ReturnRequest;

#[Fillable([
    'order_id', 'payment_id', 'return_id', 'provider', 'idempotency_key', 'provider_refund_id',
    'amount', 'currency', 'status', 'reason', 'created_by', 'requested_at',
    'completed_at', 'failed_at', 'metadata',
])]
class Refund extends Model
{
    use BelongsToStore, HasFactory, HasUlid;

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'status' => RefundStatus::class,
            'amount' => 'decimal:2',
            'requested_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function returnRequest(): BelongsTo
    {
        return $this->belongsTo(ReturnRequest::class, 'return_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
