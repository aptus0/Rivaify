<?php

namespace Modules\Ecosystem\Models;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['store_id', 'integration_key', 'type', 'message', 'metadata', 'created_at'])]
class IntegrationActivityLog extends Model
{
    use BelongsToStore, HasUlid;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
