<?php

namespace App\Core\Internal\Models;

use App\Core\Shared\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['staff_role_id', 'name', 'email', 'password', 'status', 'last_login_at'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes'])]
class StaffUser extends Model
{
    use HasUlid;

    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(StaffRole::class, 'staff_role_id');
    }

    public function cases(): HasMany
    {
        return $this->hasMany(OperationCase::class, 'assigned_to');
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->role?->permissions ?? [], true);
    }
}
