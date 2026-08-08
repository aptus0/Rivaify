<?php

namespace Modules\Commerce\Models\Cart;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Commerce\Enums\Cart\CartStatus;
use Modules\Commerce\Models\Checkout\CheckoutSession;
use Modules\Commerce\Models\Customer\Customer;
use Modules\Commerce\Models\Discount\Discount;
use Modules\Commerce\Models\Tax\TaxRate;

#[Fillable([
    'customer_id', 'discount_id', 'discount_code', 'user_id', 'token', 'status', 'currency', 'subtotal',
    'discount_total', 'tax_total', 'tax_inclusive', 'tax_rate_id', 'shipping_total', 'grand_total', 'expires_at',
])]
class Cart extends Model
{
    use BelongsToStore, HasFactory, HasUlid;

    protected $attributes = [
        'status' => 'active',
        'currency' => 'TRY',
        'subtotal' => 0,
        'discount_total' => 0,
        'tax_total' => 0,
        'tax_inclusive' => false,
        'shipping_total' => 0,
        'grand_total' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => CartStatus::class,
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'tax_inclusive' => 'boolean',
            'shipping_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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

    public function items(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function checkoutSessions(): HasMany
    {
        return $this->hasMany(CheckoutSession::class);
    }
}