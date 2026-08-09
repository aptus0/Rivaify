<?php

namespace Modules\Commerce\Models\Finance;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'provider', 'provider_settlement_id', 'gross', 'fees', 'refunds', 'net', 'expected_net',
    'difference', 'currency', 'status', 'period_start', 'period_end', 'expected_at', 'paid_at',
])]
class MerchantSettlement extends Model
{
    use BelongsToStore, HasFactory, HasUlid;

    protected $attributes = [
        'status' => 'pending',
        'currency' => 'TRY',
    ];

    protected function casts(): array
    {
        return [
            'gross' => 'decimal:2',
            'fees' => 'decimal:2',
            'refunds' => 'decimal:2',
            'net' => 'decimal:2',
            'expected_net' => 'decimal:2',
            'difference' => 'decimal:2',
            'period_start' => 'date',
            'period_end' => 'date',
            'expected_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }
}
