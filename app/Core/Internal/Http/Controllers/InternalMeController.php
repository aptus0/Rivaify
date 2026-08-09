<?php

namespace App\Core\Internal\Http\Controllers;

use App\Core\Internal\Support\InternalStaff;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class InternalMeController extends Controller
{
    public function __invoke(InternalStaff $staff): JsonResponse
    {
        $current = $staff->current();

        return response()->json(['data' => [
            'authenticated' => $current !== null,
            'staff' => $current ? [
                'id' => $current->ulid,
                'name' => $current->name,
                'email' => $current->email,
                'role' => [
                    'key' => $current->role?->key,
                    'name' => $current->role?->name,
                    'permissions' => $current->role?->permissions ?? [],
                ],
                'two_factor_enabled' => $current->two_factor_confirmed_at !== null,
                'last_login_at' => $current->last_login_at?->toIso8601String(),
            ] : null,
        ]]);
    }
}
