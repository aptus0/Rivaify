<?php

namespace Modules\Commerce\Http\Controllers\Admin;

use App\Core\Tenancy\CurrentStore;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Enums\Order\PaymentStatus;
use Modules\Commerce\Models\Customer\Customer;
use Modules\Commerce\Models\Order\Order;
use Modules\Commerce\Services\Analytics\TrafficAnalytics;
use Modules\Commerce\ValueObjects\Money;

class AdminAnalyticsController extends Controller
{
    private const SALES_PAYMENT_STATUSES = [
        PaymentStatus::Paid->value,
        PaymentStatus::PartiallyRefunded->value,
        PaymentStatus::Refunded->value,
    ];

    public function show(Request $request, CurrentStore $currentStore, TrafficAnalytics $traffic): JsonResponse
    {
        $validated = $request->validate(['range' => ['nullable', 'in:7d,30d,90d']]);
        $days = match ($validated['range'] ?? '30d') {
            '7d' => 7, '90d' => 90, default => 30
        };
        $store = $currentStore->store();
        $toInStore = now($store->timezone)->endOfDay();
        $fromInStore = $toInStore->copy()->subDays($days - 1)->startOfDay();
        $previousToInStore = $fromInStore->copy()->subSecond();
        $previousFromInStore = $previousToInStore->copy()->subDays($days - 1)->startOfDay();
        $to = $toInStore->copy()->utc();
        $from = $fromInStore->copy()->utc();
        $previousTo = $previousToInStore->copy()->utc();
        $previousFrom = $previousFromInStore->copy()->utc();

        $current = $this->metrics($from, $to, $store->default_currency, $store->id);
        $previous = $this->metrics($previousFrom, $previousTo, $store->default_currency, $store->id);
        $series = $this->salesSeries($from, $to, $store->default_currency, $store->timezone, $store->id);
        $topProducts = DB::table('order_items')->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.store_id', $store->id)
            ->where('orders.store_id', $store->id)
            ->whereIn('orders.payment_status', self::SALES_PAYMENT_STATUSES)
            ->where('orders.currency', $store->default_currency)
            ->whereBetween('orders.placed_at', [$from, $to])
            ->selectRaw('order_items.product_title AS title, SUM(order_items.quantity) AS quantity, SUM(order_items.line_total) AS revenue')
            ->groupBy('order_items.product_title')->orderByDesc('revenue')->limit(10)->get();
        $paymentBreakdown = Order::query()
            ->where('currency', $store->default_currency)
            ->whereBetween('placed_at', [$from, $to])
            ->selectRaw('payment_status, COUNT(*) AS total')->groupBy('payment_status')->orderByDesc('total')->get();

        return response()->json(['data' => [
            'range' => $validated['range'] ?? '30d',
            'currency' => $store->default_currency,
            'period' => ['from' => $from->toIso8601String(), 'to' => $to->toIso8601String()],
            'metrics' => $current + ['returning_customers' => Customer::query()->where('total_orders', '>', 1)->count()],
            'changes' => collect(['net_sales', 'orders', 'average_order', 'new_customers'])->mapWithKeys(
                fn (string $key): array => [$key => $this->change((float) $current[$key], (float) $previous[$key])]
            ),
            'series' => $series,
            'top_products' => $topProducts->map(fn ($row): array => ['title' => $row->title, 'quantity' => (int) $row->quantity, 'revenue' => (string) $row->revenue]),
            'top_products_basis' => 'gross_order_item_revenue_excludes_refunds',
            'payment_breakdown' => $paymentBreakdown->map(fn ($row): array => ['status' => $row->payment_status, 'total' => (int) $row->total]),
            'traffic' => $traffic->summarize($from, $to),
        ]]);
    }

    private function metrics($from, $to, string $currency, int $storeId): array
    {
        $paid = Order::query()->whereIn('payment_status', self::SALES_PAYMENT_STATUSES)->where('currency', $currency)->whereBetween('placed_at', [$from, $to]);
        $orders = (clone $paid)->count();
        $gross = Money::fromDecimal((string) (clone $paid)->sum('grand_total'), $currency);
        $refunds = Money::fromDecimal((string) DB::table('payment_transactions as transactions')
            ->join('payments', 'payments.id', '=', 'transactions.payment_id')
            ->where('transactions.store_id', $storeId)
            ->where('payments.store_id', $storeId)
            ->where('payments.currency', $currency)
            ->where('transactions.type', 'refund')
            ->where('transactions.status', 'succeeded')
            ->whereBetween('transactions.created_at', [$from, $to])
            ->sum('transactions.amount'), $currency);
        $net = $gross->subtract($refunds);
        $average = $orders === 0
            ? Money::zero($currency)
            : Money::fromMinor((int) round($net->amount / $orders, 0, PHP_ROUND_HALF_UP), $currency);

        return [
            'net_sales' => $net->toDecimal(),
            'refunds' => $refunds->toDecimal(),
            'orders' => $orders,
            'average_order' => $average->toDecimal(),
            'new_customers' => Customer::query()->whereBetween('created_at', [$from, $to])->count(),
        ];
    }

    /** @return list<array{date: string, sales: string, gross_sales: string, refunds: string, orders: int}> */
    private function salesSeries($from, $to, string $currency, string $timezone, int $storeId): array
    {
        $orders = DB::table('orders')
            ->where('store_id', $storeId)
            ->whereIn('payment_status', self::SALES_PAYMENT_STATUSES)
            ->where('currency', $currency)
            ->whereBetween('placed_at', [$from, $to])
            ->selectRaw("DATE(placed_at AT TIME ZONE 'UTC' AT TIME ZONE ?) AS day", [$timezone])
            ->selectRaw('SUM(grand_total) AS sales, COUNT(*) AS orders')
            ->groupByRaw('1')
            ->get()
            ->keyBy(fn (object $row): string => (string) $row->day);
        $refunds = DB::table('payment_transactions as transactions')
            ->join('payments', 'payments.id', '=', 'transactions.payment_id')
            ->where('transactions.store_id', $storeId)
            ->where('payments.store_id', $storeId)
            ->where('payments.currency', $currency)
            ->where('transactions.type', 'refund')
            ->where('transactions.status', 'succeeded')
            ->whereBetween('transactions.created_at', [$from, $to])
            ->selectRaw("DATE(transactions.created_at AT TIME ZONE 'UTC' AT TIME ZONE ?) AS day", [$timezone])
            ->selectRaw('SUM(transactions.amount) AS refunds')
            ->groupByRaw('1')
            ->get()
            ->keyBy(fn (object $row): string => (string) $row->day);

        return $orders->keys()
            ->merge($refunds->keys())
            ->unique()
            ->sort()
            ->map(function (string $day) use ($orders, $refunds, $currency): array {
                $gross = Money::fromDecimal((string) ($orders->get($day)?->sales ?? '0.00'), $currency);
                $refunded = Money::fromDecimal((string) ($refunds->get($day)?->refunds ?? '0.00'), $currency);

                return [
                    'date' => $day,
                    'sales' => $gross->subtract($refunded)->toDecimal(),
                    'gross_sales' => $gross->toDecimal(),
                    'refunds' => $refunded->toDecimal(),
                    'orders' => (int) ($orders->get($day)?->orders ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    private function change(float $current, float $previous): ?float
    {
        if ($previous === 0.0) {
            return $current === 0.0 ? 0.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
