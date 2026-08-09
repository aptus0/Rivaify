<?php

namespace Modules\Commerce\Models\Storefront;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['store_theme_id', 'theme_version_id', 'manifest', 'published_at'])]
class StorefrontPublication extends Model
{
    use BelongsToStore, HasUlid;

    protected function casts(): array
    {
        return [
            'manifest' => 'array',
            'published_at' => 'datetime',
        ];
    }
}
