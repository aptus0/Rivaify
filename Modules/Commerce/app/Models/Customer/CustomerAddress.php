<?php

namespace Modules\Commerce\Models\Customer;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Commerce\Enums\Customer\CustomerAddressType;

#[Fillable([
    'customer_id', 'type', 'first_name', 'last_name', 'company', 'phone', 'country_code',
    'province', 'district', 'address_line_1', 'address_line_2', 'postal_code', 'is_default',
])]
class CustomerAddress extends Model
{
    use BelongsToStore, HasFactory, HasUlid;

    protected $attributes = [
        'type' => 'shipping',
        'is_default' => false,
    ];

    protected function casts(): array
    {
        return [
            'type' => CustomerAddressType::class,
            'is_default' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}