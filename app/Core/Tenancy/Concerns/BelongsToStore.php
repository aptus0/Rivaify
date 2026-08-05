<?php

namespace App\Core\Tenancy\Concerns;

use App\Core\Tenancy\CurrentStore;
use App\Core\Tenancy\Scopes\StoreScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Store\Models\Store;

/**
 * Apply to every model that has a `store_id` column. Combined with
 * StoreScope this is what makes tenant isolation automatic rather than a
 * convention every developer has to remember (brief: "hiçbir query direkt
 * Order::all() olamaz").
 *
 * @mixin Model
 */
trait BelongsToStore
{
    protected static function bootBelongsToStore(): void
    {
        static::addGlobalScope(new StoreScope);

        static::creating(function (Model $model): void {
            $currentStore = app(CurrentStore::class);
            if (empty($model->store_id) && $currentStore->has()) {
                $model->store_id = $currentStore->id();
            }
        });
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
