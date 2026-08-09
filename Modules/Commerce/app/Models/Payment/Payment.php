<?php

namespace Modules\Commerce\Models\Payment;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Commerce\Enums\Payment\PaymentStatus;
use Modules\Commerce\Models\Checkout\CheckoutSession;
use Modules\Commerce\Models\Order\Order;

#[Fillable([
    'order_id', 'checkout_id', 'provider', 'provider_payment_id', 'status', 'amount', 'currency',
    'payment_method_type', 'authorized_at', 'paid_at', 'failed_at', 'failure_code', 'failure_message', 'metadata',
])]
class Payment extends Model
{
    use BelongsToStore, HasFactory, HasUlid;

    protected $attributes = [
        'status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'authorized_at' => 'datetime',
            'paid_at' => 'datetime',
            'failed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(CheckoutSession::class, 'checkout_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PaymentTransaction::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }
}
