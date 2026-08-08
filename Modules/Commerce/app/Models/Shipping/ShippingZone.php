<?php

namespace Modules\Commerce\Models\Shipping;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class ShippingZone extends Model
{
    use BelongsToStore, HasFactory, HasUlid;

    public function regions(): HasMany
    {
        return $this->hasMany(ShippingZoneRegion::class);
    }

    public function methods(): HasMany
    {
        return $this->hasMany(ShippingMethod::class);
    }
}