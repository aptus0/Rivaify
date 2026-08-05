<?php

namespace Modules\Store\Models;

use App\Core\Shared\Concerns\HasUlid;
use App\Core\Tenancy\Concerns\BelongsToStore;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Store\Enums\StoreUserRole;
use Modules\Store\Enums\StoreUserStatus;

#[Fillable(['store_id', 'user_id', 'role', 'status', 'invited_by', 'joined_at'])]
class StoreUser extends Model
{
    use BelongsToStore, HasFactory, HasUlid;

    protected function casts(): array
    {
        return [
            'role' => StoreUserRole::class,
            'status' => StoreUserStatus::class,
            'joined_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }
}
