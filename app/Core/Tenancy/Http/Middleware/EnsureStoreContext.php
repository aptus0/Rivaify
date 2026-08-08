<?php

namespace App\Core\Tenancy\Http\Middleware;

use App\Core\Tenancy\CurrentStore;
use App\Core\Tenancy\Scopes\StoreScope;
use Closure;
use Illuminate\Http\Request;
use Modules\Store\Enums\StoreUserStatus;
use Modules\Store\Models\Store;
use Modules\Store\Models\StoreUser;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves which store the current request operates on and binds it into
 * CurrentStore for the rest of the request lifecycle. Every dashboard/API
 * route that touches store-scoped data must run through this middleware —
 * routes that don't (registration, onboarding before a store exists,
 * Rivaify Admin) deliberately skip it.
 *
 * The active-store id lives in the session (brief: "session + Redis"),
 * set by a separate "switch store" action — this middleware only reads it
 * and verifies membership, it never chooses a store on its own.
 */
class EnsureStoreContext
{
    public function __construct(private readonly CurrentStore $currentStore) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            abort(401);
        }

        if (! $request->hasSession()) {
            return response()->json(['message' => 'store_context_session_required'], 409);
        }

        $storeId = $request->session()->get('current_store_id');
        if ($storeId === null) {
            return response()->json(['message' => 'no_store_selected'], 409);
        }

        $store = Store::query()->find($storeId);
        if ($store === null) {
            $request->session()->forget('current_store_id');

            return response()->json(['message' => 'store_not_found'], 404);
        }

        // Bootstrapping query: CurrentStore isn't bound yet at this point,
        // so StoreUser's own StoreScope must be deliberately bypassed here
        // — this is the one sanctioned place that happens (see StoreScope's
        // docblock).
        $isMember = StoreUser::withoutGlobalScope(StoreScope::class)
            ->where('store_id', $store->id)
            ->where('user_id', $user->id)
            ->where('status', StoreUserStatus::Active)
            ->exists();

        if (! $isMember) {
            return response()->json(['message' => 'store_access_denied'], 403);
        }

        $this->currentStore->set($store);

        return $next($request);
    }
}
