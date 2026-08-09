<?php

namespace Modules\Commerce\Models\Returns;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Commerce\Enums\Returns\ReturnStatus;
use Modules\Commerce\Models\Customer\Customer;
use Modules\Commerce\Models\Order\Order;
use Modules\Commerce\Models\Payment\Refund;

#[Fillable([
    'order_id', 'customer_id', 'return_number', 'status', 'reason', 'customer_note',
    'internal_note', 'return_tracking_number', 'return_tracking_url', 'requested_at',
    'approved_at', 'received_at', 'completed_at',
])]
class ReturnRequest extends Model
{
    use BelongsToStore, HasFactory, HasUlid;

    protected $table = 'returns';

    protected $attributes = [
        'status' => 'requested',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReturnStatus::class,
            'requested_at' => 'datetime',
            'approved_at' => 'datetime',
            'received_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class, 'return_id');
    }
}
