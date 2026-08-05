<?php

namespace Modules\Merchant\Models;

use App\Core\Shared\Concerns\HasUlid;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Merchant\Enums\MerchantStatus;
use Modules\Merchant\Enums\MerchantType;
use Modules\Store\Models\Store;
use Modules\Verification\Models\VerificationRequest;

/**
 * Not store-scoped — a merchant sits above the store level (it owns
 * stores, brief §4), so it does not use BelongsToStore.
 */
#[Fillable(['owner_user_id', 'type', 'status'])]
class Merchant extends Model
{
    use HasFactory, HasUlid;

    /**
     * Mirrors the DB column defaults so a freshly `create()`d instance has
     * these populated immediately — Eloquent does not read back
     * DB-computed defaults after INSERT, so without this the in-memory
     * object's enum casts would resolve to null until the model is
     * refreshed from the database.
     */
    protected $attributes = [
        'type' => 'individual',
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'type' => MerchantType::class,
            'status' => MerchantStatus::class,
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    public function businessProfile(): HasOne
    {
        return $this->hasOne(BusinessProfile::class);
    }

    public function taxProfile(): HasOne
    {
        return $this->hasOne(TaxProfile::class);
    }

    public function verificationRequests(): HasMany
    {
        return $this->hasMany(VerificationRequest::class);
    }
}
