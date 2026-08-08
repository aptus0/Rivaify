<?php

namespace Modules\Commerce\Models\Discount;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Commerce\Models\Checkout\CheckoutSession;
use Modules\Commerce\Models\Customer\Customer;

#[Fillable(['discount_id', 'customer_id', 'checkout_id', 'order_id'])]
class DiscountUsage extends Model
{
    use BelongsToStore, HasFactory, HasUlid;

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function checkout(): BelongsTo
    {
        return $this->belongsTo(CheckoutSession::class, 'checkout_id');
    }
}