<?php

namespace Modules\Merchant\Models;

use App\Core\Shared\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['legal_name', 'trade_name', 'registration_number', 'contact_email', 'contact_phone', 'submitted_at'])]
class BusinessProfile extends Model
{
    use HasFactory, HasUlid;

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
        ];
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(BusinessAddress::class);
    }
}
