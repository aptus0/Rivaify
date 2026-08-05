<?php

namespace App\Core\Tenancy\Scopes;

use App\Core\Tenancy\CurrentStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Automatically constrains every query on a BelongsToStore model to the
 * currently bound store. This is the mechanism that makes `Order::all()`
 * safe to write — it can never return another tenant's rows, because this
 * scope is applied before the query ever runs.
 *
 * Rivaify Admin (cross-tenant) code must opt out explicitly and
 * deliberately via `Order::withoutGlobalScope(StoreScope::class)`, which is
 * greppable/auditable — unlike a missing `->where('store_id', ...)` that
 * silently does nothing.
 */
class StoreScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $currentStore = app(CurrentStore::class);

        $builder->where($model->qualifyColumn('store_id'), $currentStore->id());
    }
}
