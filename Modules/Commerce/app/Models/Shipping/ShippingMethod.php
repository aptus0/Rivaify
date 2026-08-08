<?php

namespace Modules\Commerce\Models\Shipping;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Commerce\Enums\Shipping\ShippingMethodStatus;
use Modules\Commerce\Enums\Shipping\ShippingMethodType;

#[Fillable([
    'shipping_zone_id', 'name', 'type', 'price', 'minimum_order', 'maximum_order',
    'estimated_days_min', 'estimated_days_max', 'status',
])]
class ShippingMethod extends Model
{
    use BelongsToStore, HasFactory, HasUlid;

    protected $attributes = [
        'type' => 'flat_rate',
        'price' => 0,
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'type' => ShippingMethodType::class,
            'status' => ShippingMethodStatus::class,
            'price' => 'decimal:2',
            'minimum_order' => 'decimal:2',
            'maximum_order' => 'decimal:2',
        ];
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(ShippingZone::class, 'shipping_zone_id');
    }
}