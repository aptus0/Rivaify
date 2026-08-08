<?php

namespace Modules\Commerce\Models\Tax;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Commerce\Enums\Tax\TaxRateStatus;

#[Fillable(['name', 'country_code', 'rate', 'is_inclusive', 'status'])]
class TaxRate extends Model
{
    use BelongsToStore, HasFactory, HasUlid;

    protected $attributes = [
        'is_inclusive' => false,
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:2',
            'is_inclusive' => 'boolean',
            'status' => TaxRateStatus::class,
        ];
    }
}