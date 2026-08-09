<?php

namespace Modules\Commerce\Services\Dashboard;

use App\Core\Tenancy\CurrentStore;
use App\Core\Tenancy\StoreRolePermissions;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Commerce\Enums\Dashboard\MerchantDashboardAudience;
use Modules\Commerce\Enums\Order\PaymentStatus as OrderPaymentStatus;
use Modules\Commerce\Enums\Order\OrderStatus;
use Modules\Commerce\Enums\Payment\PaymentStatus as GatewayPaymentStatus;
use Modules\Commerce\Http\Presenters\OrderPresenter;
use Modules\Commerce\Models\Customer\Customer;
use Modules\Commerce\Models\Order\Order;
use Modules\Commerce\ValueObjects\Money;
use Modules\Store\Enums\StoreUserStatus;
use Modules\Store\Models\Store;
use Modules\Store\Models\StoreUser;

class MerchantDashboardService
{
    /** @var list<string> */
    private const RANGES = ['today', '7d', '30d'];

    public function __construct(
        private readonly CurrentStore $currentStore,
        private readonly MerchantDashboardCache $cache,
    ) {}

    /** @return array<string, mixed> */
    public function get(User $user, string $range = 'today'): array
    {
        if (! in_array($range, self::RANGES, true)) {
            throw new InvalidArgumentException("Unsupported dashboard range [{$range}].");
        }

        $store = $this->currentStore->store();
        $membership = StoreUser::query()
            ->where('store_id', $store->id)
            ->where('user_id', $user->id)
            ->where('status', StoreUserStatus::Active)
            ->first();
        if ($membership === null) {
            throw new AuthorizationException('Active store membership is required.');
        }
        $audience = MerchantDashboardAudience::fromRole($membership->role);
        $data = $this->cache->remember(
            $store->id,
            $range,
            $audience,
            fn (): array => $this->build($store, $range, $audience),
        );

        return [
            ...$data,
            'capabilities' => StoreRolePermissions::capabilities($membership->role),
        ];
    }

    /** @return array<string, mixed> */
    private function build(Store $store, string $range, MerchantDashboardAudience $audience): array
    {
        $period = $this->period($range, $store->timezone);
        $data = [
            'audience' => $audience->value,
            'visibility' => $audience->visibility(),
            'currency' => $store->default_currency,
            'range' => $range,
            'period' => [
                'from' => $period['from']->toIso8601String(),
                'to' => $period['to']->toIso8601String(),
                'previous_from' => $period['previous_from']->toIso8601String(),
                'previous_to' => $period['previous_to']->toIso8601String(),
                'timezone' => $store->timezone,
            ],
        ];
        $previousPeriod = [];
        $changes = [];
        $orderCounts = null;

        if ($audience->canViewSales()) {
            $metrics = $this->getMetrics(
                $store->id,
                $store->default_currency,
                $period['from'],
                $period['to'],
                $period['previous_from'],
            );
            $currentSales = Money::fromDecimal($metrics['current_sales'], $store->default_currency)
                ->subtract(Money::fromDecimal($metrics['current_refunds'], $store->default_currency));
            $previousSales = Money::fromDecimal($metrics['previous_sales'], $store->default_currency)
                ->subtract(Money::fromDecimal($metrics['previous_refunds'], $store->default_currency));
            $currentAverage = $this->average($currentSales, $metrics['current_orders']);
            $previousAverage = $this->average($previousSales, $metrics['previous_orders']);
            $orderCounts = [
                'current' => $metrics['current_orders'],
                'previous' => $metrics['previous_orders'],
            ];

            $data += [
                'sales' => $currentSales->toDecimal(),
                'refunds' => Money::fromDecimal($metrics['current_refunds'], $store->default_currency)->toDecimal(),
                'average_order' => $currentAverage->toDecimal(),
                'sales_series' => $this->getSalesSeries(
                    $store->id,
                    $store->default_currency,
                    $store->timezone,
                    $period['from'],
                    $period['to'],
                ),
                'top_products' => $this->getTopProducts($store->id, $period['from'], $period['to']),
            ];
            $previousPeriod += [
                'sales' => $previousSales->toDecimal(),
                'refunds' => Money::fromDecimal($metrics['previous_refunds'], $store->default_currency)->toDecimal(),
                'average_order' => $previousAverage->toDecimal(),
            ];
            $changes += [
                'sales' => $this->change($currentSales->amount, $previousSales->amount),
                'average_order' => $this->change($currentAverage->amount, $previousAverage->amount),
            ];
        }

        if ($audience->canViewOrders()) {
            $orderCounts ??= $this->getOrderCounts(
                $store->id,
                $store->default_currency,
                $period['from'],
                $period['to'],
                $period['previous_from'],
            );
            $data += [
                'orders' => $orderCounts['current'],
                'recent_orders' => $this->getRecentOrders($audience->canViewCustomers()),
                'order_status' => $this->getOrderStatus($store->id),
            ];
            $previousPeriod['orders'] = $orderCounts['previous'];
            $changes['orders'] = $this->change($orderCounts['current'], $orderCounts['previous']);
        }

        if ($audience->canViewInventory()) {
            $data['inventory'] = $this->getInventorySummary($store->id);
        }

        if ($audience->canViewCustomers()) {
            $customers = $this->getCustomerSummary(
                $store->id,
                $store->default_currency,
                $period['from'],
                $period['to'],
                $period['previous_from'],
            );
            $data += [
                'customers' => $customers['new_customers'],
                'customer_summary' => $customers,
            ];
            $previousPeriod['customers'] = $customers['previous_new_customers'];
            $changes['customers'] = $this->change($customers['new_customers'], $customers['previous_new_customers']);
        }

        if ($previousPeriod !== []) {
            $data['previous_period'] = $previousPeriod;
            $data['changes'] = $changes;
        }

        return $data;
    }

    /**
     * @return array{
     *     from: CarbonInterface,
     *     to: CarbonInterface,
     *     previous_from: CarbonInterface,
     *     previous_to: CarbonInterface
     * }
     */
    private function period(string $range, string $timezone): array
    {
        $days = match ($range) {
            '7d' => 7,
            '30d' => 30,
            default => 1,
        };
        $localNow = now($timezone);
        $localFrom = $localNow->copy()->subDays($days - 1)->startOfDay();

        return [
            'from' => $localFrom->copy()->utc(),
            'to' => $localNow->copy()->endOfDay()->utc(),
            'previous_from' => $localFrom->copy()->subDays($days)->startOfDay()->utc(),
            'previous_to' => $localFrom->copy()->subMicrosecond()->utc(),
        ];
    }

    /** @return array{current: int, previous: int} */
    private function getOrderCounts(
        int $storeId,
        string $currency,
        CarbonInterface $from,
        CarbonInterface $to,
        CarbonInterface $previousFrom,
    ): array {
        $row = DB::table('orders')
            ->where('store_id', $storeId)
            ->whereIn('payment_status', [
                OrderPaymentStatus::Paid->value,
                OrderPaymentStatus::PartiallyRefunded->value,
                OrderPaymentStatus::Refunded->value,
            ])
            ->where('currency', $currency)
            ->whereBetween('placed_at', [$previousFrom, $to])
            ->selectRaw('SUM(CASE WHEN placed_at >= ? THEN 1 ELSE 0 END) AS current_orders', [$from])
            ->selectRaw('SUM(CASE WHEN placed_at < ? THEN 1 ELSE 0 END) AS previous_orders', [$from])
            ->first();

        return [
            'current' => (int) ($row->current_orders ?? 0),
            'previous' => (int) ($row->previous_orders ?? 0),
        ];
    }

    /**
     * @return array{
     *     current_sales: string,
     *     current_orders: int,
     *     current_refunds: string,
     *     previous_sales: string,
     *     previous_orders: int,
     *     previous_refunds: string
     * }
     */
    private function getMetrics(
        int $storeId,
        string $currency,
        CarbonInterface $from,
        CarbonInterface $to,
        CarbonInterface $previousFrom,
    ): array {
        $row = DB::table('orders')
            ->where('store_id', $storeId)
            ->whereIn('payment_status', [
                OrderPaymentStatus::Paid->value,
                OrderPaymentStatus::PartiallyRefunded->value,
                OrderPaymentStatus::Refunded->value,
            ])
            ->where('currency', $currency)
            ->whereBetween('placed_at', [$previousFrom, $to])
            ->selectRaw('COALESCE(SUM(CASE WHEN placed_at >= ? THEN grand_total ELSE 0 END), 0) AS current_sales', [$from])
            ->selectRaw('SUM(CASE WHEN placed_at >= ? THEN 1 ELSE 0 END) AS current_orders', [$from])
            ->selectRaw('COALESCE(SUM(CASE WHEN placed_at < ? THEN grand_total ELSE 0 END), 0) AS previous_sales', [$from])
            ->selectRaw('SUM(CASE WHEN placed_at < ? THEN 1 ELSE 0 END) AS previous_orders', [$from])
            ->first();

        $refunds = DB::table('payment_transactions as transactions')
            ->join('payments', 'payments.id', '=', 'transactions.payment_id')
            ->where('transactions.store_id', $storeId)
            ->where('payments.store_id', $storeId)
            ->where('payments.currency', $currency)
            ->where('transactions.type', 'refund')
            ->where('transactions.status', 'succeeded')
            ->whereBetween('transactions.created_at', [$previousFrom, $to])
            ->selectRaw('COALESCE(SUM(CASE WHEN transactions.created_at >= ? THEN transactions.amount ELSE 0 END), 0) AS current_refunds', [$from])
            ->selectRaw('COALESCE(SUM(CASE WHEN transactions.created_at < ? THEN transactions.amount ELSE 0 END), 0) AS previous_refunds', [$from])
            ->first();

        return [
            'current_sales' => (string) ($row->current_sales ?? '0.00'),
            'current_orders' => (int) ($row->current_orders ?? 0),
            'current_refunds' => (string) ($refunds->current_refunds ?? '0.00'),
            'previous_sales' => (string) ($row->previous_sales ?? '0.00'),
            'previous_orders' => (int) ($row->previous_orders ?? 0),
            'previous_refunds' => (string) ($refunds->previous_refunds ?? '0.00'),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function getSalesSeries(
        int $storeId,
        string $currency,
        string $timezone,
        CarbonInterface $from,
        CarbonInterface $to,
    ): array {
        $orders = DB::table('orders')
            ->where('store_id', $storeId)
            ->whereIn('payment_status', [
                OrderPaymentStatus::Paid->value,
                OrderPaymentStatus::PartiallyRefunded->value,
                OrderPaymentStatus::Refunded->value,
            ])
            ->where('currency', $currency)
            ->whereBetween('placed_at', [$from, $to])
            ->selectRaw("DATE(placed_at AT TIME ZONE 'UTC' AT TIME ZONE ?) AS day", [$timezone])
            ->selectRaw('SUM(grand_total) AS sales, COUNT(*) AS orders')
            ->groupByRaw('1')
            ->orderBy('day')
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
                    'orders' => (int) ($orders->get($day)?->orders ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    /** @return list<array<string, mixed>> */
    private function getRecentOrders(bool $withCustomerDetails): array
    {
        $query = Order::query()
            ->orderByDesc('placed_at')
            ->limit(8);
        if ($withCustomerDetails) {
            $query->with('customer');
        }

        return $query->get()
            ->map(fn (Order $order): array => $withCustomerDetails
                ? OrderPresenter::summary($order)
                : [
                    'id' => $order->ulid,
                    'number' => $order->order_number,
                    'status' => $order->status->value,
                    'payment_status' => $order->payment_status->value,
                    'fulfillment_status' => $order->fulfillment_status->value,
                    'customer' => ['name' => null, 'email' => $order->customer_email],
                    'currency' => $order->currency,
                    'grand_total' => $order->grand_total,
                    'placed_at' => $order->placed_at?->toIso8601String(),
                ])
            ->values()
            ->all();
    }

    /** @return array{unfulfilled: int, shipping: int, payment_pending: int, returns: int, failed_payments: int} */
    private function getOrderStatus(int $storeId): array
    {
        $statuses = DB::table('orders')
            ->where('store_id', $storeId)
            ->whereNotIn('status', [OrderStatus::Cancelled->value, OrderStatus::Archived->value])
            ->selectRaw('payment_status, fulfillment_status, COUNT(*) AS total')
            ->groupBy('payment_status', 'fulfillment_status')
            ->get();

        $failedPayments = DB::table('payments')
            ->where('store_id', $storeId)
            ->where('status', GatewayPaymentStatus::Failed->value)
            ->count();

        return [
            'unfulfilled' => (int) $statuses->where('fulfillment_status', 'unfulfilled')->sum('total'),
            'shipping' => (int) $statuses->where('fulfillment_status', 'partial')->sum('total'),
            'payment_pending' => (int) $statuses->where('payment_status', 'pending')->sum('total'),
            'returns' => (int) $statuses->where('fulfillment_status', 'returned')->sum('total'),
            'failed_payments' => $failedPayments,
        ];
    }

    /** @return array{available: int, low: int, out: int} */
    private function getInventorySummary(int $storeId): array
    {
        $row = DB::table('inventory_levels')
            ->where('store_id', $storeId)
            ->selectRaw('COALESCE(SUM(available_quantity - reserved_quantity), 0) AS available')
            ->selectRaw('SUM(CASE WHEN available_quantity - reserved_quantity BETWEEN 1 AND 5 THEN 1 ELSE 0 END) AS low')
            ->selectRaw('SUM(CASE WHEN available_quantity - reserved_quantity <= 0 THEN 1 ELSE 0 END) AS out')
            ->first();

        return [
            'available' => (int) ($row->available ?? 0),
            'low' => (int) ($row->low ?? 0),
            'out' => (int) ($row->out ?? 0),
        ];
    }

    /** @return list<array{title: string, quantity: int, revenue: string}> */
    private function getTopProducts(int $storeId, CarbonInterface $from, CarbonInterface $to): array
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('order_items.store_id', $storeId)
            ->where('orders.store_id', $storeId)
            ->whereIn('orders.payment_status', [
                OrderPaymentStatus::Paid->value,
                OrderPaymentStatus::PartiallyRefunded->value,
            ])
            ->whereBetween('orders.placed_at', [$from, $to])
            ->selectRaw('order_items.product_title AS title, SUM(order_items.quantity) AS quantity, SUM(order_items.line_total) AS revenue')
            ->groupBy('order_items.product_title')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get()
            ->map(fn (object $row): array => [
                'title' => (string) $row->title,
                'quantity' => (int) $row->quantity,
                'revenue' => (string) $row->revenue,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{
     *     total_customers: int,
     *     new_customers: int,
     *     previous_new_customers: int,
     *     new_customers_change: float|null,
     *     purchasing_customers: int,
     *     returning_customers: int,
     *     returning_rate: float,
     *     recent_customers: list<array<string, mixed>>
     * }
     */
    private function getCustomerSummary(
        int $storeId,
        string $currency,
        CarbonInterface $from,
        CarbonInterface $to,
        CarbonInterface $previousFrom,
    ): array {
        $counts = DB::table('customers')
            ->where('store_id', $storeId)
            ->whereNull('deleted_at')
            ->selectRaw('COUNT(*) AS total_customers')
            ->selectRaw('SUM(CASE WHEN created_at >= ? AND created_at <= ? THEN 1 ELSE 0 END) AS new_customers', [$from, $to])
            ->selectRaw('SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) AS previous_new_customers', [$previousFrom, $from])
            ->first();
        $newCustomers = (int) ($counts->new_customers ?? 0);
        $previousNewCustomers = (int) ($counts->previous_new_customers ?? 0);

        $historicalCustomers = DB::table('orders')
            ->where('store_id', $storeId)
            ->whereIn('payment_status', [
                OrderPaymentStatus::Paid->value,
                OrderPaymentStatus::PartiallyRefunded->value,
            ])
            ->where('currency', $currency)
            ->whereNotNull('customer_id')
            ->where('placed_at', '<', $from)
            ->select(['store_id', 'customer_id'])
            ->groupBy('store_id', 'customer_id');
        $purchases = DB::table('orders as current_orders')
            ->leftJoinSub($historicalCustomers, 'historical_customers', function ($join): void {
                $join->on('historical_customers.store_id', '=', 'current_orders.store_id')
                    ->on('historical_customers.customer_id', '=', 'current_orders.customer_id');
            })
            ->where('current_orders.store_id', $storeId)
            ->whereIn('current_orders.payment_status', [
                OrderPaymentStatus::Paid->value,
                OrderPaymentStatus::PartiallyRefunded->value,
            ])
            ->where('current_orders.currency', $currency)
            ->whereNotNull('current_orders.customer_id')
            ->whereBetween('current_orders.placed_at', [$from, $to])
            ->selectRaw('COUNT(DISTINCT current_orders.customer_id) AS purchasing_customers')
            ->selectRaw('COUNT(DISTINCT CASE WHEN historical_customers.customer_id IS NOT NULL THEN current_orders.customer_id END) AS returning_customers')
            ->first();
        $purchasingCustomers = (int) ($purchases->purchasing_customers ?? 0);
        $returningCustomers = (int) ($purchases->returning_customers ?? 0);

        $recentCustomers = Customer::query()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get()
            ->map(function (Customer $customer): array {
                $name = trim("{$customer->first_name} {$customer->last_name}");

                return [
                    'id' => $customer->ulid,
                    'name' => $name === '' ? null : $name,
                    'email' => $customer->email,
                    'total_orders' => $customer->total_orders,
                    'total_spent' => $customer->total_spent,
                    'last_order_at' => $customer->last_order_at?->toIso8601String(),
                    'created_at' => $customer->created_at?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        return [
            'total_customers' => (int) ($counts->total_customers ?? 0),
            'new_customers' => $newCustomers,
            'previous_new_customers' => $previousNewCustomers,
            'new_customers_change' => $this->change($newCustomers, $previousNewCustomers),
            'purchasing_customers' => $purchasingCustomers,
            'returning_customers' => $returningCustomers,
            'returning_rate' => $purchasingCustomers === 0
                ? 0.0
                : round(($returningCustomers / $purchasingCustomers) * 100, 1),
            'recent_customers' => $recentCustomers,
        ];
    }

    private function average(Money $sales, int $orders): Money
    {
        if ($orders === 0) {
            return Money::zero($sales->currency);
        }

        return Money::fromMinor(
            intdiv($sales->amount + intdiv($orders, 2), $orders),
            $sales->currency,
        );
    }

    private function change(int|float $current, int|float $previous): ?float
    {
        if ((float) $previous === 0.0) {
            return (float) $current === 0.0 ? 0.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
