<?php

namespace App\Core\Shared\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Adds a public, sortable ULID identity alongside the internal bigint `id`.
 * The bigint stays the primary key (fast joins/indexes); `ulid` is what
 * gets exposed in URLs and API responses so internal row counts/order are
 * never leaked (brief: "Database'teki gerçek id değerini dışarı vermeyelim").
 *
 * @mixin Model
 */
trait HasUlid
{
    protected static function bootHasUlid(): void
    {
        static::creating(function (Model $model): void {
            if (empty($model->{$model->getUlidColumn()})) {
                $model->{$model->getUlidColumn()} = (string) Str::ulid();
            }
        });
    }

    public function getUlidColumn(): string
    {
        return 'ulid';
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where($field ?? $this->getUlidColumn(), $value)->firstOrFail();
    }
}
