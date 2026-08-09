<?php

namespace Modules\Commerce\Models\Storefront;

use App\Core\Shared\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['namespace', 'name', 'trust_level', 'metadata'])]
class ThemePublisher extends Model
{
    use HasUlid;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function releases(): HasMany
    {
        return $this->hasMany(ThemeRelease::class, 'publisher_id');
    }
}
