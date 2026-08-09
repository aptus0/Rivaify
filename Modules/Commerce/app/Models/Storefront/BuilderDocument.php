<?php

namespace Modules\Commerce\Models\Storefront;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['store_theme_id', 'resource_type', 'resource_key', 'schema_version', 'document', 'revision', 'created_by', 'updated_by'])]
class BuilderDocument extends Model
{
    use BelongsToStore, HasUlid;

    protected $attributes = [
        'resource_type' => 'home',
        'resource_key' => 'home',
        'schema_version' => 1,
        'revision' => 1,
    ];

    protected function casts(): array
    {
        return [
            'document' => 'array',
            'schema_version' => 'integer',
            'revision' => 'integer',
        ];
    }

    public function storeTheme(): BelongsTo
    {
        return $this->belongsTo(StoreTheme::class);
    }
}
