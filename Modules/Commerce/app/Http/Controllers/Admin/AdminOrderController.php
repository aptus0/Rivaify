<?php

namespace Modules\Commerce\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Enums\Order\FulfillmentStatus;
use Modules\Commerce\Enums\Order\OrderStatus;
use Modules\Commerce\Enums\Order\PaymentStatus;
use Modules\Commerce\Http\Presenters\OrderPresenter;
use Modules\Commerce\Models\Order\Order;
use Modules\Commerce\Services\Payment\PaymentManager;
use Modules\Commerce\Services\Order\OrderManager;
use Modules\Commerce\ValueObjects\Money;

class AdminOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'in:'.implode(',', array_map(fn (OrderStatus $status) => $status->value, OrderStatus::cases()))],
            'payment_status' => ['nullable', 'string', 'in:'.implode(',', array_map(fn (PaymentStatus $status) => $status->value, PaymentStatus::cases()))],
            'fulfillment_status' => ['nullable', 'string', 'in:'.implode(',', array_map(fn (FulfillmentStatus $status) => $status->value, FulfillmentStatus::cases()))],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $orders = Order::query()->with('customer')->orderByDesc('placed_at');

        $this->applyFilters($orders, $validated);
        $orders = $orders->paginate($validated['per_page'] ?? 25);

        return response()->json([
            'data' => $orders->getCollection()->map(fn (Order $order): array => OrderPresenter::summary($order))->values(),
            'meta' => $this->meta($orders),
        ]);
    }

    public function show(string $ulid): JsonResponse
    {
        $order = Order::query()
            ->with(['customer', 'items', 'addresses', 'taxLines', 'events', 'payments'])
            ->where('ulid', $ulid)
            ->firstOrFail();

        return response()->json(['data' => OrderPresenter::detail($order)]);
    }

    public function cancel(string $ulid, OrderManager $orders): JsonResponse
    {
        $order = Order::query()->where('ulid', $ulid)->firstOrFail();

        return response()->json(['data' => OrderPresenter::detail($orders->cancel($order))]);
    }

    public function refundPayment(Request $request, string $ulid, string $paymentUlid, PaymentManager $payments): JsonResponse
    {
        $request->validate(['amount' => ['required', 'numeric', 'gt:0']]);
        $order = Order::query()->with('payments')->where('ulid', $ulid)->firstOrFail();
        $payment = $order->payments()->where('ulid', $paymentUlid)->firstOrFail();

        try {
            $payments->refund($payment, Money::fromDecimal((string) $request->input('amount'), $payment->currency));
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => OrderPresenter::detail($order->fresh()->load([
            'customer', 'items', 'addresses', 'taxLines', 'events', 'payments',
        ]))]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $like = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $query) use ($like): void {
                $query
                    ->whereRaw('LOWER(order_number) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(customer_email) LIKE ?', [$like])
                    ->orWhereHas('customer', function (Builder $query) use ($like): void {
                        $query
                            ->whereRaw('LOWER(first_name) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                            ->orWhereRaw('LOWER(email) LIKE ?', [$like]);
                    });
            });
        }

        foreach (['status', 'payment_status', 'fulfillment_status'] as $field) {
            if (isset($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (isset($filters['from'])) {
            $query->whereDate('placed_at', '>=', $filters['from']);
        }
        if (isset($filters['to'])) {
            $query->whereDate('placed_at', '<=', $filters['to']);
        }
    }

    /**
     * @return array<string, int>
     */
    private function meta($paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}