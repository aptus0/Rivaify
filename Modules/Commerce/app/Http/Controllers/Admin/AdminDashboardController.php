<?php

namespace Modules\Commerce\Http\Controllers\Admin;

use App\Core\Tenancy\CurrentStore;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Enums\Order\PaymentStatus;
use Modules\Commerce\Http\Presenters\OrderPresenter;
use Modules\Commerce\Models\Customer\Customer;
use Modules\Commerce\Models\Order\Order;
use Modules\Commerce\ValueObjects\Money;

class AdminDashboardController extends Controller
{
    public function show(Request $request, CurrentStore $currentStore): JsonResponse
    {
        $validated = $request->validate(['range' => ['nullable', 'in:today,7d,30d']]);
        $store = $currentStore->store();
        [$from, $to] = $this->range($validated['range'] ?? 'today', $store->timezone);
        $currency = $store->default_currency;
        $paidOrders = Order::query()
            ->where('payment_status', PaymentStatus::Paid->value)
            ->where('currency', $currency)
            ->whereBetween('placed_at', [$from, $to]);
        $orderCount = (clone $paidOrders)->count();
        $sales = Money::fromDecimal((string) (clone $paidOrders)->sum('grand_total'), $currency);
        $average = $orderCount === 0
            ? Money::zero($currency)
            : Money::fromMinor(intdiv($sales->amount + intdiv($orderCount, 2), $orderCount), $currency);
        $customers = Customer::query()->whereBetween('created_at', [$from, $to])->count();
        $recentOrders = Order::query()->with('customer')->orderByDesc('placed_at')->limit(8)->get();

        return response()->json(['data' => [
            'currency' => $currency,
            'range' => $validated['range'] ?? 'today',
            'sales' => $sales->toDecimal(),
            'orders' => $orderCount,
            'average_order' => $average->toDecimal(),
            'customers' => $customers,
            'recent_orders' => $recentOrders->map(fn (Order $order): array => OrderPresenter::summary($order))->values(),
        ]]);
    }

    /**
     * @return array{0: \Carbon\CarbonInterface, 1: \Carbon\CarbonInterface}
     */
    private function range(string $range, string $timezone): array
    {
        $now = now($timezone);

        return match ($range) {
            '7d' => [$now->copy()->subDays(6)->startOfDay()->utc(), $now->copy()->endOfDay()->utc()],
            '30d' => [$now->copy()->subDays(29)->startOfDay()->utc(), $now->copy()->endOfDay()->utc()],
            default => [$now->copy()->startOfDay()->utc(), $now->copy()->endOfDay()->utc()],
        };
    }
}