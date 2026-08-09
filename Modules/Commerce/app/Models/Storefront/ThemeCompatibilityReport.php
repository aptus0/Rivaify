<?php

namespace Modules\Commerce\Models\Storefront;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['theme_package_id', 'theme_release_id', 'status', 'stages', 'errors', 'warnings', 'summary'])]
class ThemeCompatibilityReport extends Model
{
    use BelongsToStore, HasUlid;

    protected function casts(): array
    {
        return [
            'stages' => 'array',
            'errors' => 'array',
            'warnings' => 'array',
            'summary' => 'array',
        ];
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(ThemePackage::class, 'theme_package_id');
    }

    public function release(): BelongsTo
    {
        return $this->belongsTo(ThemeRelease::class, 'theme_release_id');
    }
}
