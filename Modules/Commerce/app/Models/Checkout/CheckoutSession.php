<?php

namespace Modules\Commerce\Models\Checkout;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Commerce\Enums\Checkout\CheckoutState;
use Modules\Commerce\Models\Cart\Cart;
use Modules\Commerce\Models\Customer\Customer;
use Modules\Commerce\Models\Customer\CustomerAddress;
use Modules\Commerce\Models\Discount\Discount;
use Modules\Commerce\Models\Shipping\ShippingMethod;
use Modules\Commerce\Models\Tax\TaxRate;
use Modules\Commerce\Models\Order\Order;
use Modules\Commerce\Models\Payment\Payment;

#[Fillable([
    'cart_id', 'customer_id', 'discount_id', 'discount_code', 'token', 'status', 'email', 'phone', 'shipping_address_id',
    'billing_address_id', 'shipping_method_id', 'subtotal', 'discount_total', 'shipping_total',
    'tax_total', 'tax_inclusive', 'tax_rate_id', 'grand_total', 'currency', 'expires_at', 'completed_at',
])]
class CheckoutSession extends Model
{
    use BelongsToStore, HasFactory, HasUlid;

    protected $attributes = [
        'status' => 'initiated',
        'currency' => 'TRY',
        'subtotal' => 0,
        'discount_total' => 0,
        'shipping_total' => 0,
        'tax_total' => 0,
        'tax_inclusive' => false,
        'grand_total' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => CheckoutState::class,
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'shipping_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'tax_inclusive' => 'boolean',
            'grand_total' => 'decimal:2',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    public function taxRate(): BelongsTo
    {
        return $this->belongsTo(TaxRate::class);
    }

    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'shipping_address_id');
    }

    public function billingAddress(): BelongsTo
    {
        return $this->belongsTo(CustomerAddress::class, 'billing_address_id');
    }

    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(ShippingMethod::class);
    }

    public function order(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Order::class, 'checkout_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class, 'checkout_id');
    }
}