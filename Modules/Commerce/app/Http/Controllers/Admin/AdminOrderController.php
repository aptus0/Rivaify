<?php

namespace Modules\Commerce\Http\Controllers\Admin;

use App\Core\Tenancy\CurrentStore;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Enums\Order\FulfillmentStatus;
use Modules\Commerce\Enums\Order\OrderStatus;
use Modules\Commerce\Enums\Order\PaymentStatus;
use Modules\Commerce\Exceptions\Payment\PaymentRefundFailedException;
use Modules\Commerce\Http\Presenters\OrderPresenter;
use Modules\Commerce\Models\Catalog\ProductVariant;
use Modules\Commerce\Models\Customer\Customer;
use Modules\Commerce\Models\Order\Order;
use Modules\Commerce\Services\Order\OrderManager;
use Modules\Commerce\Services\Order\OrderNumberGenerator;
use Modules\Commerce\Services\Order\OrderTimeline;
use Modules\Commerce\Services\Payment\PaymentManager;
use Modules\Commerce\ValueObjects\Money;

class AdminOrderController extends Controller
{
    public function createOptions(Request $request, CurrentStore $currentStore): JsonResponse
    {
        $search = trim((string) $request->input('q', ''));
        $variants = ProductVariant::query()
            ->with('product')
            ->where('status', 'active')
            ->whereHas('product', fn (Builder $query) => $query->where('status', 'active'));
        if ($search !== '') {
            $like = '%'.mb_strtolower($search).'%';
            $variants->where(function (Builder $query) use ($like): void {
                $query
                    ->whereHas('product', fn (Builder $product) => $product->whereRaw('LOWER(title) LIKE ?', [$like]))
                    ->orWhereRaw('LOWER(sku) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(barcode) LIKE ?', [$like]);
            });
        }

        return response()->json(['data' => [
            'currency' => $currentStore->store()->default_currency,
            'variants' => $variants->limit(100)->get()->map(fn ($variant) => ['id' => $variant->ulid, 'title' => $variant->product->title, 'variant_title' => $variant->title, 'sku' => $variant->sku, 'price' => $variant->price]),
            'customers' => Customer::query()->orderBy('first_name')->limit(100)->get()->map(fn ($customer) => ['id' => $customer->ulid, 'name' => trim($customer->first_name.' '.$customer->last_name) ?: $customer->email, 'email' => $customer->email]),
        ]]);
    }

    public function store(Request $request, CurrentStore $currentStore, OrderNumberGenerator $numbers, OrderTimeline $timeline): JsonResponse
    {
        $validated = $request->validate(['customer_id' => ['nullable', 'string', 'size:26'], 'items' => ['required', 'array', 'min:1'], 'items.*.variant_id' => ['required', 'string', 'size:26', 'distinct'], 'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'], 'shipping_total' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'], 'notes' => ['nullable', 'string', 'max:5000']]);
        $customer = isset($validated['customer_id']) ? Customer::query()->where('ulid', $validated['customer_id'])->firstOrFail() : null;
        $variantIds = collect($validated['items'])->pluck('variant_id')->all();
        $variants = ProductVariant::query()
            ->with('product')
            ->where('status', 'active')
            ->whereHas('product', fn (Builder $query) => $query->where('status', 'active'))
            ->whereIn('ulid', $variantIds)
            ->get()
            ->keyBy('ulid');
        if ($variants->count() !== count(array_unique($variantIds))) {
            return response()->json(['message' => 'Bir veya daha fazla ürün varyantı bulunamadı.'], 422);
        }
        $currency = $currentStore->store()->default_currency;
        $order = DB::transaction(function () use ($validated, $customer, $variants, $currency, $numbers, $timeline) {
            $subtotal = collect($validated['items'])->reduce(fn (Money $total, array $item) => $total->add(Money::fromDecimal($variants[$item['variant_id']]->price, $currency)->multiply($item['quantity'])), Money::zero($currency));
            $shipping = Money::fromDecimal((string) ($validated['shipping_total'] ?? '0'), $currency);
            $order = Order::query()->create(['customer_id' => $customer?->id, 'order_number' => $numbers->next(), 'currency' => $currency, 'subtotal' => $subtotal->toDecimal(), 'shipping_total' => $shipping->toDecimal(), 'grand_total' => $subtotal->add($shipping)->toDecimal(), 'customer_email' => $customer?->email, 'customer_phone' => $customer?->phone, 'notes' => $validated['notes'] ?? null, 'placed_at' => now()]);
            foreach ($validated['items'] as $item) {
                $variant = $variants[$item['variant_id']];
                $unit = Money::fromDecimal($variant->price, $currency);
                $order->items()->create(['product_id' => $variant->product_id, 'variant_id' => $variant->id, 'product_title' => $variant->product->title, 'variant_title' => $variant->title, 'sku' => $variant->sku, 'quantity' => $item['quantity'], 'unit_price' => $unit->toDecimal(), 'line_total' => $unit->multiply($item['quantity'])->toDecimal()]);
            }
            $timeline->record($order, 'manual_order_created', 'Manual order created from merchant admin.', actorType: 'user', actorId: request()->user()?->id);

            return $order->load(['customer', 'items', 'addresses', 'taxLines', 'events', 'payments']);
        });

        return response()->json(['data' => OrderPresenter::detail($order)], 201);
    }

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

    public function updateFulfillment(Request $request, string $ulid, OrderManager $orders): JsonResponse
    {
        $validated = $request->validate(['status' => ['required', 'in:unfulfilled,partial,fulfilled,returned']]);
        $order = Order::query()->where('ulid', $ulid)->firstOrFail();
        try {
            $order = $orders->updateFulfillment($order, FulfillmentStatus::from($validated['status']));
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => OrderPresenter::detail($order)]);
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
        } catch (PaymentRefundFailedException) {
            return response()->json(['message' => 'payment_refund_failed'], 502);
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
