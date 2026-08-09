<?php

namespace Modules\Commerce\Models\Payment;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Modules\Commerce\Enums\Payment\PaymentAccountPayoutStatus;
use Modules\Commerce\Enums\Payment\PaymentAccountVerificationStatus;
use Modules\Commerce\Enums\Payment\StorePaymentAccountStatus;

/**
 * Links a store to its PayTR Marketplace submerchant identity. No
 * provider secrets/API keys live here in plaintext — see the migration's
 * docblock.
 */
#[Fillable([
    'provider', 'provider_account_id', 'provider_submerchant_id', 'status',
    'verification_status', 'payout_status', 'capabilities', 'metadata', 'connected_at',
])]
class StorePaymentAccount extends Model
{
    use BelongsToStore, HasFactory, HasUlid;

    protected $attributes = [
        'status' => 'pending_verification',
        'verification_status' => 'not_started',
        'payout_status' => 'ineligible',
    ];

    protected function casts(): array
    {
        return [
            'status' => StorePaymentAccountStatus::class,
            'verification_status' => PaymentAccountVerificationStatus::class,
            'payout_status' => PaymentAccountPayoutStatus::class,
            'capabilities' => 'array',
            'metadata' => 'array',
            'connected_at' => 'datetime',
        ];
    }
}
