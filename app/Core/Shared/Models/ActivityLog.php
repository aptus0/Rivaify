<?php

namespace App\Core\Shared\Models;

use App\Core\Shared\Concerns\HasUlid;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Store\Models\Store;

/**
 * Deliberately NOT BelongsToStore: activity happens before a store exists
 * (registration) as well as within one, and this is the one place Rivaify
 * Admin needs a cross-tenant view by default. Scoping it would force every
 * write through an active CurrentStore and hide store-less/global events
 * from a merchant's own audit trail — write store_id explicitly instead
 * (see App\Core\Shared\Services\ActivityLogger).
 */
#[Fillable(['event', 'properties', 'subject_type', 'subject_id', 'store_id', 'user_id'])]
class ActivityLog extends Model
{
    use HasUlid;

    protected function casts(): array
    {
        return [
            'properties' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
