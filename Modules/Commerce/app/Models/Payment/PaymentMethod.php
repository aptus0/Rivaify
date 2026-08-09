<?php

namespace Modules\Commerce\Models\Payment;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Commerce\Enums\Payment\PaymentMethodStatus;
use Modules\Commerce\Models\Customer\Customer;

/**
 * A saved card is only ever the provider's own tokens — see the
 * migration's docblock. Never add a column here that could reconstruct a
 * real PAN/CVV.
 */
#[Fillable([
    'customer_id', 'provider', 'provider_customer_token', 'provider_card_token',
    'brand', 'last4', 'expiry_month', 'expiry_year', 'status', 'is_default', 'last_used_at',
])]
class PaymentMethod extends Model
{
    use BelongsToStore, HasFactory, HasUlid;

    protected $attributes = [
        'status' => 'active',
        'is_default' => false,
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentMethodStatus::class,
            'expiry_month' => 'integer',
            'expiry_year' => 'integer',
            'is_default' => 'boolean',
            'last_used_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
