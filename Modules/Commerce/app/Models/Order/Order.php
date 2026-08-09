<?php

namespace Modules\Commerce\Models\Order;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Commerce\Enums\Order\FulfillmentStatus;
use Modules\Commerce\Enums\Order\OrderStatus;
use Modules\Commerce\Enums\Order\PaymentStatus;
use Modules\Commerce\Models\Checkout\CheckoutSession;
use Modules\Commerce\Models\Customer\Customer;
use Modules\Commerce\Models\Fulfillment\Fulfillment;
use Modules\Commerce\Models\Payment\Payment;
use Modules\Commerce\Models\Payment\Refund;
use Modules\Commerce\Models\Returns\ReturnRequest;
use Modules\Commerce\Models\Shipping\Shipment;

#[Fillable([
    'customer_id', 'checkout_id', 'order_number', 'status', 'payment_status', 'fulfillment_status',
    'currency', 'subtotal', 'discount_total', 'tax_total', 'shipping_total', 'grand_total',
    'customer_email', 'customer_phone', 'notes', 'placed_at', 'cancelled_at', 'closed_at',
])]
class Order extends Model
{
    use BelongsToStore, HasFactory, HasUlid;

    protected $attributes = [
        'status' => 'open',
        'payment_status' => 'pending',
        'fulfillment_status' => 'unfulfilled',
        'currency' => 'TRY',
        'subtotal' => 0,
        'discount_total' => 0,
        'tax_total' => 0,
        'shipping_total' => 0,
        'grand_total' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'fulfillment_status' => FulfillmentStatus::class,
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'shipping_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'placed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(CheckoutSession::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(OrderAddress::class);
    }

    public function taxLines(): HasMany
    {
        return $this->hasMany(OrderTaxLine::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(OrderEvent::class)->orderBy('created_at');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function fulfillments(): HasMany
    {
        return $this->hasMany(Fulfillment::class);
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(Shipment::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(ReturnRequest::class);
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(Refund::class);
    }
}
