<?php

namespace Modules\Commerce\Models\Storefront;

use App\Core\Shared\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['key', 'name', 'version', 'category', 'defaults'])]
class Theme extends Model
{
    use HasUlid;

    protected function casts(): array
    {
        return [
            'defaults' => 'array',
        ];
    }

    public function storeThemes(): HasMany
    {
        return $this->hasMany(StoreTheme::class);
    }

    public function releases(): HasMany
    {
        return $this->hasMany(ThemeRelease::class);
    }
}
