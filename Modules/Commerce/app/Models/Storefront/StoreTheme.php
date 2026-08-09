<?php

namespace Modules\Commerce\Models\Storefront;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['theme_id', 'theme_release_id', 'name', 'status', 'source', 'trust_level', 'tokens', 'published_version_id', 'published_at'])]
class StoreTheme extends Model
{
    use BelongsToStore, HasUlid;

    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'tokens' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function baseTheme(): BelongsTo
    {
        return $this->belongsTo(Theme::class, 'theme_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(BuilderDocument::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ThemeVersion::class);
    }

    public function publishedVersion(): BelongsTo
    {
        return $this->belongsTo(ThemeVersion::class, 'published_version_id');
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(ThemeRelease::class, 'theme_release_id');
    }
}
