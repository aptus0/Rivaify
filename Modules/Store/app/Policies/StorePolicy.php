<?php

namespace Modules\Store\Policies;

use App\Core\Tenancy\Scopes\StoreScope;
use App\Models\User;
use Modules\Store\Enums\StoreUserRole;
use Modules\Store\Enums\StoreUserStatus;
use Modules\Store\Models\Store;
use Modules\Store\Models\StoreUser;

/**
 * Deliberately queries StoreUser with the global scope bypassed rather
 * than relying on CurrentStore being bound to the *same* store being
 * checked — an authorization check must be correct regardless of
 * whatever the ambient tenant context happens to be, not just in the
 * common case where they match.
 *
 * Granular per-permission checks (products.view, orders.update, ...) are
 * an explicit future extension (brief §6) — this is role-based only.
 */
class StorePolicy
{
    public function view(User $user, Store $store): bool
    {
        return $this->membership($user, $store) !== null;
    }

    public function update(User $user, Store $store): bool
    {
        return $this->hasRole($user, $store, [StoreUserRole::Owner, StoreUserRole::Admin]);
    }

    public function manageUsers(User $user, Store $store): bool
    {
        return $this->hasRole($user, $store, [StoreUserRole::Owner, StoreUserRole::Admin]);
    }

    public function manageSettings(User $user, Store $store): bool
    {
        return $this->hasRole($user, $store, [StoreUserRole::Owner, StoreUserRole::Admin]);
    }

    /**
     * @param  StoreUserRole[]  $roles
     */
    private function hasRole(User $user, Store $store, array $roles): bool
    {
        $membership = $this->membership($user, $store);

        return $membership !== null && in_array($membership->role, $roles, true);
    }

    private function membership(User $user, Store $store): ?StoreUser
    {
        return StoreUser::withoutGlobalScope(StoreScope::class)
            ->where('store_id', $store->id)
            ->where('user_id', $user->id)
            ->where('status', StoreUserStatus::Active)
            ->first();
    }
}
