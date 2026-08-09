<?php

namespace Modules\Commerce\Models\Storefront;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['store_theme_id', 'version', 'status', 'snapshot', 'created_by', 'published_at'])]
class ThemeVersion extends Model
{
    use BelongsToStore, HasUlid;

    protected $attributes = [
        'status' => 'saved',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'snapshot' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function storeTheme(): BelongsTo
    {
        return $this->belongsTo(StoreTheme::class);
    }
}
