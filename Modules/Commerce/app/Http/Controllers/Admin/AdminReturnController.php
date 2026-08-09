<?php

namespace Modules\Commerce\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Enums\Returns\ReturnStatus;
use Modules\Commerce\Models\Order\Order;
use Modules\Commerce\Models\Returns\ReturnRequest;
use Modules\Commerce\Services\Returns\ReturnManager;
use Modules\Commerce\ValueObjects\Money;

class AdminReturnController extends Controller
{
    public function index(): JsonResponse
    {
        $summary = [
            'requested' => ReturnRequest::query()->where('status', ReturnStatus::Requested)->count(),
            'under_review' => ReturnRequest::query()->where('status', ReturnStatus::UnderReview)->count(),
            'return_shipping' => ReturnRequest::query()->where('status', ReturnStatus::ReturnShipping)->count(),
            'received' => ReturnRequest::query()->where('status', ReturnStatus::Received)->count(),
            'refund_pending' => ReturnRequest::query()->where('status', ReturnStatus::RefundPending)->count(),
        ];
        $returns = ReturnRequest::query()
            ->with(['order.customer', 'items.orderItem', 'refunds'])
            ->latest('requested_at')
            ->limit(50)
            ->get()
            ->map(fn (ReturnRequest $return): array => $this->present($return));

        return response()->json(['data' => compact('summary', 'returns')]);
    }

    public function store(Request $request, ReturnManager $manager): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'string', 'size:26'],
            'reason' => ['nullable', 'string', 'max:255'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.order_item_id' => ['required', 'string', 'size:26'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'items.*.reason_code' => ['nullable', 'string', 'max:50'],
            'items.*.resolution' => ['nullable', 'string', 'max:50'],
        ]);
        $order = Order::query()->where('ulid', $validated['order_id'])->firstOrFail();
        try {
            $return = $manager->request($order, $validated['items'], $validated['reason'] ?? null, $validated['customer_note'] ?? null);
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $this->present($return)], 201);
    }

    public function approve(Request $request, string $returnUlid, ReturnManager $manager): JsonResponse
    {
        $validated = $request->validate(['internal_note' => ['nullable', 'string', 'max:2000']]);
        $return = ReturnRequest::query()->where('ulid', $returnUlid)->firstOrFail();

        return response()->json(['data' => $this->present($manager->approve($return, $validated['internal_note'] ?? null))]);
    }

    public function receive(string $returnUlid, ReturnManager $manager): JsonResponse
    {
        $return = ReturnRequest::query()->where('ulid', $returnUlid)->firstOrFail();

        return response()->json(['data' => $this->present($manager->receive($return))]);
    }

    public function inspect(Request $request, string $returnUlid, ReturnManager $manager): JsonResponse
    {
        $validated = $request->validate([
            'location_id' => ['nullable', 'integer'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.return_item_id' => ['required', 'string', 'size:26'],
            'items.*.condition' => ['required', 'string', 'max:50'],
            'items.*.restock' => ['required', 'boolean'],
        ]);
        $return = ReturnRequest::query()->where('ulid', $returnUlid)->firstOrFail();

        return response()->json(['data' => $this->present($manager->inspect($return, $validated['items'], $validated['location_id'] ?? null))]);
    }

    public function refund(Request $request, string $returnUlid, ReturnManager $manager): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0'],
            'idempotency_key' => ['required', 'string', 'max:120'],
        ]);
        $return = ReturnRequest::query()->with('order')->where('ulid', $returnUlid)->firstOrFail();
        try {
            $manager->refund($return, Money::fromDecimal((string) $validated['amount'], $return->order->currency), $validated['idempotency_key'], request()->user()?->id);
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $this->present($return->fresh()->load(['order.customer', 'items.orderItem', 'refunds']))]);
    }

    private function present(ReturnRequest $return): array
    {
        $return->loadMissing(['order.customer', 'items.orderItem', 'refunds']);

        return [
            'id' => $return->ulid,
            'number' => $return->return_number,
            'status' => $return->status->value,
            'reason' => $return->reason,
            'customer_note' => $return->customer_note,
            'internal_note' => $return->internal_note,
            'return_tracking_number' => $return->return_tracking_number,
            'requested_at' => $return->requested_at?->toIso8601String(),
            'approved_at' => $return->approved_at?->toIso8601String(),
            'received_at' => $return->received_at?->toIso8601String(),
            'completed_at' => $return->completed_at?->toIso8601String(),
            'order' => [
                'id' => $return->order->ulid,
                'number' => $return->order->order_number,
                'currency' => $return->order->currency,
                'grand_total' => $return->order->grand_total,
            ],
            'items' => $return->items->map(fn ($item): array => [
                'id' => $item->ulid,
                'order_item_id' => $item->orderItem->ulid,
                'title' => $item->orderItem->product_title,
                'variant_title' => $item->orderItem->variant_title,
                'quantity' => $item->quantity,
                'reason_code' => $item->reason_code,
                'condition' => $item->condition,
                'resolution' => $item->resolution,
                'restock' => $item->restock,
            ])->values()->all(),
            'refunds' => $return->refunds->map(fn ($refund): array => [
                'id' => $refund->ulid,
                'status' => $refund->status->value,
                'amount' => $refund->amount,
                'currency' => $refund->currency,
                'completed_at' => $refund->completed_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
