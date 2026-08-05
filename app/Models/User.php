<?php

namespace App\Models;

use App\Core\Shared\Concerns\HasUlid;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Modules\Identity\Enums\UserStatus;
use Modules\Merchant\Models\Merchant;

#[Fillable(['name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasUlid, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'status' => UserStatus::class,
            'is_rivaify_admin' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /**
     * Sprint 01 assumes one merchant per user (brief §4) — see
     * Modules\Store\Exceptions\MerchantAlreadyHasStoreException.
     */
    public function merchant(): HasOne
    {
        return $this->hasOne(Merchant::class, 'owner_user_id');
    }
}
