<?php

namespace Modules\Commerce\Models\Finance;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Commerce\Enums\Finance\FinancialTransactionType;
use Modules\Commerce\Models\Order\Order;

#[Fillable([
    'order_id', 'type', 'gross_amount', 'platform_fee', 'provider_fee', 'net_amount',
    'currency', 'reference_type', 'reference_id', 'status', 'occurred_at',
])]
class FinancialTransaction extends Model
{
    use BelongsToStore, HasFactory, HasUlid;

    protected $attributes = [
        'status' => 'posted',
    ];

    protected function casts(): array
    {
        return [
            'type' => FinancialTransactionType::class,
            'gross_amount' => 'decimal:2',
            'platform_fee' => 'decimal:2',
            'provider_fee' => 'decimal:2',
            'net_amount' => 'decimal:2',
            'occurred_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
