<?php

namespace App\Core\Internal\Models;

use App\Core\Shared\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'staff_user_id', 'action', 'resource_type', 'resource_id', 'request_id', 'session_id_hash',
    'reason', 'before_state', 'after_state', 'metadata', 'ip_hash', 'created_at',
])]
class AdminAuditLog extends Model
{
    use HasUlid;

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'before_state' => 'array',
            'after_state' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function staffUser(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class);
    }
}
