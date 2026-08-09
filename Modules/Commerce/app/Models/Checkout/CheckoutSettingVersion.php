<?php

namespace Modules\Commerce\Models\Checkout;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An immutable published snapshot — never updated after creation. This is
 * what checkout.rivaify.com actually renders (the latest row per
 * checkout_setting_id), never the mutable CheckoutSetting draft directly.
 */
#[Fillable(['checkout_setting_id', 'version', 'snapshot', 'published_by', 'published_at'])]
class CheckoutSettingVersion extends Model
{
    use BelongsToStore, HasFactory, HasUlid;

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function checkoutSetting(): BelongsTo
    {
        return $this->belongsTo(CheckoutSetting::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }
}
