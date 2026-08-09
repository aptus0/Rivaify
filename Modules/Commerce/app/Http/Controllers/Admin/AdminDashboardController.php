<?php

namespace Modules\Commerce\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Services\Dashboard\MerchantDashboardService;

class AdminDashboardController extends Controller
{
    public function show(Request $request, MerchantDashboardService $dashboard): JsonResponse
    {
        $validated = $request->validate(['range' => ['nullable', 'in:today,7d,30d']]);
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }

        return response()->json([
            'data' => $dashboard->get($user, $validated['range'] ?? 'today'),
        ]);
    }
}
