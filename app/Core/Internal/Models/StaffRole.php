<?php

namespace App\Core\Internal\Models;

use App\Core\Shared\Concerns\HasUlid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['key', 'name', 'permissions'])]
class StaffRole extends Model
{
    use HasUlid;

    protected function casts(): array
    {
        return [
            'permissions' => 'array',
        ];
    }

    public function staffUsers(): HasMany
    {
        return $this->hasMany(StaffUser::class);
    }
}
