<?php

namespace Modules\Commerce\Models\Storefront;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'theme_id', 'theme_release_id', 'source', 'trust_level', 'status', 'original_filename',
    'quarantine_disk', 'quarantine_path', 'sha256', 'size_bytes', 'manifest', 'file_index',
    'security_report', 'validated_at', 'installed_at',
])]
class ThemePackage extends Model
{
    use BelongsToStore, HasUlid;

    protected function casts(): array
    {
        return [
            'manifest' => 'array',
            'file_index' => 'array',
            'security_report' => 'array',
            'validated_at' => 'datetime',
            'installed_at' => 'datetime',
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

    public function report(): HasOne
    {
        return $this->hasOne(ThemeCompatibilityReport::class);
    }
}
