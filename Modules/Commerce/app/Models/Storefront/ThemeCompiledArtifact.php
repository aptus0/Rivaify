<?php

namespace Modules\Commerce\Models\Storefront;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'theme_id', 'theme_release_id', 'theme_package_id', 'engine_version',
    'artifact_version', 'checksum', 'artifact',
])]
class ThemeCompiledArtifact extends Model
{
    use BelongsToStore, HasUlid;

    protected function casts(): array
    {
        return [
            'artifact' => 'array',
        ];
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(ThemeRelease::class, 'theme_release_id');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ThemePackage::class, 'theme_package_id');
    }
}
