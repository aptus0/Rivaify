<?php

namespace Modules\Commerce\Models\Order;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Commerce\Enums\Order\OrderAddressType;

#[Fillable([
    'order_id', 'type', 'first_name', 'last_name', 'company', 'phone', 'country_code', 'province',
    'district', 'address_line_1', 'address_line_2', 'postal_code',
])]
class OrderAddress extends Model
{
    use BelongsToStore, HasUlid;

    public const UPDATED_AT = null;

    protected function casts(): array
    {
        return ['type' => OrderAddressType::class];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}