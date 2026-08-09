<?php

namespace App\Core\Tenancy\Http\Middleware;

use App\Core\Tenancy\CurrentStore;
use App\Core\Tenancy\StoreRolePermissions;
use Closure;
use Illuminate\Http\Request;
use Modules\Store\Enums\StoreUserStatus;
use Modules\Store\Models\StoreUser;
use Symfony\Component\HttpFoundation\Response;

class EnsureStorePermission
{
    public function __construct(private readonly CurrentStore $currentStore) {}

    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        $membership = StoreUser::query()
            ->where('store_id', $this->currentStore->id())
            ->where('user_id', $user->id)
            ->where('status', StoreUserStatus::Active)
            ->first();
        if ($membership === null || ! StoreRolePermissions::allows($membership->role, $permission)) {
            abort(403);
        }

        return $next($request);
    }
}
