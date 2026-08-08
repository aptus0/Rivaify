<?php

namespace Modules\Commerce\Models\Discount;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Commerce\Enums\Discount\DiscountStatus;
use Modules\Commerce\Enums\Discount\DiscountType;

#[Fillable([
    'name', 'code', 'type', 'value', 'status', 'starts_at', 'ends_at', 'usage_limit',
    'usage_count', 'minimum_purchase',
])]
class Discount extends Model
{
    use BelongsToStore, HasFactory, HasUlid;

    protected $attributes = [
        'type' => 'percentage',
        'value' => 0,
        'status' => 'active',
        'usage_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'type' => DiscountType::class,
            'status' => DiscountStatus::class,
            'value' => 'decimal:2',
            'minimum_purchase' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function conditions(): HasMany
    {
        return $this->hasMany(DiscountCondition::class);
    }

    public function usages(): HasMany
    {
        return $this->hasMany(DiscountUsage::class);
    }
}