<?php

namespace App\Core\Internal\Support;

use App\Core\Internal\Models\StaffRole;
use App\Core\Internal\Models\StaffUser;
use App\Models\User;

class InternalStaff
{
    public function current(): ?StaffUser
    {
        $request = request();
        $user = $request->user('sanctum') ?? $request->user();

        if (! $user instanceof User || ! $user->is_rivaify_admin) {
            return null;
        }

        return $this->fromUser($user);
    }

    public function fromUser(User $user): StaffUser
    {
        $staff = StaffUser::query()->with('role')->where('email', $user->email)->first();

        if ($staff !== null) {
            return $staff;
        }

        $role = StaffRole::query()->firstOrCreate(
            ['key' => 'super_admin'],
            ['name' => 'Super Admin', 'permissions' => ['*']],
        );

        return StaffUser::query()->create([
            'staff_role_id' => $role->id,
            'name' => $user->name,
            'email' => $user->email,
            'password' => $user->password,
            'status' => 'active',
            'last_login_at' => $user->last_login_at,
        ])->load('role');
    }
}
