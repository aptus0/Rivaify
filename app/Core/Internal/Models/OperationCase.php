<?php

namespace App\Core\Internal\Models;

use App\Core\Shared\Concerns\HasUlid;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Models\Store;

#[Fillable([
    'case_number', 'type', 'priority', 'status', 'store_id', 'merchant_id', 'user_id',
    'resource_type', 'resource_id', 'assigned_to', 'title', 'summary', 'metadata',
    'opened_at', 'due_at', 'resolved_at', 'closed_at',
])]
class OperationCase extends Model
{
    use HasFactory, HasUlid;

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'opened_at' => 'datetime',
            'due_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Merchant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(StaffUser::class, 'assigned_to');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(OperationCaseNote::class);
    }
}
