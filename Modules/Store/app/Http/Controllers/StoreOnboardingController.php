<?php

namespace Modules\Store\Http\Controllers;

use App\Core\Tenancy\CurrentStore;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Store\Actions\CreateStore;
use Modules\Store\DTOs\CreateStoreData;
use Modules\Store\Exceptions\MerchantAlreadyHasStoreException;

class StoreOnboardingController extends Controller
{
    public function store(Request $request, CreateStore $action): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        try {
            $store = $action->handle($request->user(), new CreateStoreData(name: $validated['name']));
        } catch (MerchantAlreadyHasStoreException) {
            return response()->json(['message' => 'merchant_already_has_store'], 409);
        }

        // The store the merchant just created becomes their active store —
        // every onboarding step after this one runs through store.context.
        $request->session()->put('current_store_id', $store->id);

        return response()->json(['data' => $this->present($store)], 201);
    }

    public function current(CurrentStore $currentStore): JsonResponse
    {
        return response()->json(['data' => $this->present($currentStore->store())]);
    }

    private function present($store): array
    {
        return [
            'id' => $store->ulid,
            'name' => $store->name,
            'slug' => $store->slug,
            'status' => $store->status->value,
            'onboarding_status' => $store->onboarding_status->value,
            'onboarding_step' => $store->onboarding_status->step(),
        ];
    }
}
