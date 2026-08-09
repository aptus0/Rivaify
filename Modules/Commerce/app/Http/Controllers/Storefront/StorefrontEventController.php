<?php

namespace Modules\Commerce\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Commerce\Enums\Analytics\StorefrontEventType;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Models\Checkout\CheckoutSession;
use Modules\Commerce\Services\Analytics\StorefrontEventRecorder;

class StorefrontEventController extends Controller
{
    public function store(Request $request, StorefrontEventRecorder $recorder): JsonResponse
    {
        if ($request->input('event_type') === StorefrontEventType::Purchase->value) {
            return response()->json(['message' => 'purchase_event_server_only'], 422);
        }

        $validated = $request->validate([
            'event_type' => ['required', 'string', Rule::in(StorefrontEventType::clientValues())],
            'session_id' => ['required', 'string', 'min:16', 'max:128', 'regex:/\A[A-Za-z0-9_-]+\z/'],
            'product_id' => ['nullable', 'required_if:event_type,product_view,add_to_cart', 'ulid'],
            'checkout_token' => ['nullable', 'required_if:event_type,checkout_started', 'string', 'max:255'],
            'path' => ['nullable', 'string', 'max:500'],
            'utm_source' => ['nullable', 'string', 'max:100'],
            'utm_medium' => ['nullable', 'string', 'max:100'],
            'utm_campaign' => ['nullable', 'string', 'max:150'],
            'referrer_host' => ['nullable', 'string', 'max:253'],
        ]);

        $type = StorefrontEventType::from($validated['event_type']);
        $product = isset($validated['product_id'])
            ? Product::query()->where('ulid', $validated['product_id'])->firstOrFail()
            : null;
        $checkout = isset($validated['checkout_token'])
            ? CheckoutSession::query()->where('token', $validated['checkout_token'])->firstOrFail()
            : null;

        try {
            $event = $recorder->recordClient(
                $type,
                $validated['session_id'],
                $product,
                $checkout,
                $validated['path'] ?? null,
                $validated,
                $request->getHost(),
            );
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json(['data' => ['accepted' => false]], 202);
        }

        return response()->json(['data' => ['accepted' => true, 'id' => $event->ulid]], 202);
    }
}
