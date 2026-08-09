<?php

namespace App\Core\Internal\Models;

use App\Core\Shared\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Store\Models\Store;

#[Fillable(['store_id', 'capability', 'enabled', 'source', 'reason'])]
class StoreCapability extends Model
{
    use HasUlid;

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
