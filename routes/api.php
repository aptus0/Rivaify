<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Core\Tenancy\Scopes\StoreScope;
use Modules\Store\Enums\StoreUserStatus;
use Modules\Store\Models\StoreUser;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Tells the SPA where to route the user after login: no merchant yet ->
// store-creation step; merchant+store exist -> resume at their current
// onboarding_status; onboarding_status is Completed -> dashboard.
Route::get('/me', function (Request $request) {
    $user = $request->user();
    $activeMemberships = StoreUser::withoutGlobalScope(StoreScope::class)
        ->with('store')
        ->where('user_id', $user->id)
        ->where('status', StoreUserStatus::Active)
        ->orderByRaw('CASE WHEN joined_at IS NULL THEN 1 ELSE 0 END')
        ->orderBy('joined_at')
        ->orderBy('id');
    $selectedStoreId = $request->session()->get('current_store_id');
    $membership = $selectedStoreId === null
        ? null
        : (clone $activeMemberships)->where('store_id', $selectedStoreId)->first();
    $membership ??= $activeMemberships->first();
    $store = $membership?->store;

    if ($store !== null) {
        $request->session()->put('current_store_id', $store->id);
    } else {
        $request->session()->forget('current_store_id');
    }

    return response()->json([
        'data' => [
            'user' => [
                'id' => $user->ulid,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified' => $user->hasVerifiedEmail(),
                'is_rivaify_admin' => (bool) $user->is_rivaify_admin,
            ],
            'store' => $store ? [
                'id' => $store->ulid,
                'name' => $store->name,
                'slug' => $store->slug,
                'status' => $store->status->value,
                'onboarding_status' => $store->onboarding_status->value,
                'onboarding_step' => $store->onboarding_status->step(),
            ] : null,
        ],
    ]);
})->middleware('auth:sanctum');
