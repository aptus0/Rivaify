<?php

use App\Core\Tenancy\Scopes\StoreScope;
use App\Core\Tenancy\StoreRolePermissions;
use App\Core\Internal\Http\Controllers\InternalCaseController;
use App\Core\Internal\Http\Controllers\InternalDashboardController;
use App\Core\Internal\Http\Controllers\InternalMeController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Modules\Store\Enums\StoreUserStatus;
use Modules\Store\Models\StoreUser;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Tells the SPA where to route the user after login: no merchant yet ->
// store-creation step; merchant+store exist -> resume at their current
// onboarding_status; onboarding_status is Completed -> dashboard.
Route::get('/me', function (Request $request) {
    // Resolve Sanctum explicitly while keeping this endpoint optional-auth:
    // guests receive a stable 200 payload and session/token users are still
    // recognized without applying the rejecting `auth:sanctum` middleware.
    $user = $request->user('sanctum');
    if ($user === null) {
        return response()->json(['data' => [
            'authenticated' => false,
            'user' => null,
            'store' => null,
        ]]);
    }
    $memberships = StoreUser::withoutGlobalScope(StoreScope::class)
        ->with('store')
        ->where('user_id', $user->id)
        ->where('status', StoreUserStatus::Active);
    $selectedStoreId = $request->hasSession()
        ? $request->session()->get('current_store_id')
        : null;
    $membership = $selectedStoreId === null
        ? null
        : (clone $memberships)->where('store_id', $selectedStoreId)->first();
    if ($selectedStoreId !== null && $membership === null && $request->hasSession()) {
        $request->session()->forget('current_store_id');
    }
    $membership ??= $memberships->orderBy('id')->first();
    $store = $membership?->store;

    return response()->json([
        'data' => [
            'authenticated' => true,
            'user' => [
                'id' => $user->ulid,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified' => $user->hasVerifiedEmail(),
                'is_rivaify_admin' => $user->is_rivaify_admin,
            ],
            'store' => $store ? [
                'id' => $store->ulid,
                'name' => $store->name,
                'slug' => $store->slug,
                'status' => $store->status->value,
                'default_currency' => $store->default_currency,
                'onboarding_status' => $store->onboarding_status->value,
                'onboarding_step' => $store->onboarding_status->step(),
                'role' => $membership->role->value,
                'permissions' => StoreRolePermissions::forRole($membership->role),
                'capabilities' => StoreRolePermissions::capabilities($membership->role),
            ] : null,
        ],
    ]);
});

Route::prefix('internal/v1')->group(function () {
    Route::get('/me', InternalMeController::class);

    Route::middleware(['auth:sanctum', 'rivaify.admin'])->group(function () {
        Route::get('/dashboard', InternalDashboardController::class);
        Route::get('/cases', [InternalCaseController::class, 'index']);
    });
});
