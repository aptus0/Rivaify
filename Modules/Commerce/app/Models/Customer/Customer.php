<?php

namespace Modules\Commerce\Models\Customer;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Commerce\Enums\Customer\CustomerStatus;
use Modules\Commerce\Models\Cart\Cart;
use Modules\Commerce\Models\Order\Order;

#[Fillable([
    'first_name', 'last_name', 'email', 'phone', 'status', 'accepts_marketing',
    'total_orders', 'total_spent', 'last_order_at',
])]
class Customer extends Model
{
    use BelongsToStore, HasFactory, HasUlid, SoftDeletes;

    protected $attributes = [
        'status' => 'active',
        'accepts_marketing' => false,
        'total_orders' => 0,
        'total_spent' => 0,
    ];

    protected function casts(): array
    {
        return [
            'status' => CustomerStatus::class,
            'accepts_marketing' => 'boolean',
            'total_spent' => 'decimal:2',
            'last_order_at' => 'datetime',
        ];
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(CustomerAddress::class)->orderByDesc('is_default');
    }

    public function carts(): HasMany
    {
        return $this->hasMany(Cart::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CustomerEvent::class)->orderBy('created_at');
    }
}