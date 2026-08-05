<?php

namespace Modules\Store\Models;

use App\Core\Shared\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Enums\OnboardingStatus;
use Modules\Store\Enums\StoreStatus;
use Modules\Verification\Models\VerificationRequest;

/**
 * The tenant boundary itself — Store does NOT use BelongsToStore (it has
 * no store_id to scope by; it IS the store). Every other tenant-scoped
 * model points back here.
 */
#[Fillable(['name', 'slug', 'status', 'onboarding_status', 'default_currency', 'default_locale', 'timezone', 'country_code'])]
class Store extends Model
{
    use HasFactory, HasUlid;

    /**
     * See Merchant::$attributes — mirrors DB defaults so these are
     * populated immediately on a freshly created instance.
     */
    protected $attributes = [
        'status' => 'draft',
        'onboarding_status' => 'account_created',
        'default_currency' => 'TRY',
        'default_locale' => 'tr',
        'timezone' => 'Europe/Istanbul',
        'country_code' => 'TR',
    ];

    protected function casts(): array
    {
        return [
            'status' => StoreStatus::class,
            'onboarding_status' => OnboardingStatus::class,
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function storeUsers(): HasMany
    {
        return $this->hasMany(StoreUser::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(StoreDomain::class);
    }

    public function verificationRequests(): HasMany
    {
        return $this->hasMany(VerificationRequest::class);
    }
}
