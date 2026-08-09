<?php

namespace Modules\Commerce\Models\Storefront;

use App\Core\Shared\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'theme_id', 'publisher_id', 'version', 'engine_constraint', 'api_version', 'riva_lang_version',
    'manifest', 'compiled_artifact_id', 'status', 'published_at',
])]
class ThemeRelease extends Model
{
    use HasUlid;

    protected function casts(): array
    {
        return [
            'manifest' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function theme(): BelongsTo
    {
        return $this->belongsTo(Theme::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(ThemePublisher::class, 'publisher_id');
    }

    public function compiledArtifact(): BelongsTo
    {
        return $this->belongsTo(ThemeCompiledArtifact::class, 'compiled_artifact_id');
    }
}
