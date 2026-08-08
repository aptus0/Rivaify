<?php

namespace Modules\Commerce\Services\Order;

use App\Core\Tenancy\CurrentStore;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Enums\Order\OrderStatus;
use Modules\Commerce\Events\Order\OrderCancelled;
use Modules\Commerce\Models\Order\Order;

class OrderManager
{
    public function __construct(
        private readonly CurrentStore $currentStore,
        private readonly OrderTimeline $timeline,
    ) {}

    public function cancel(Order $order): Order
    {
        if ($order->store_id !== $this->currentStore->id()) {
            throw new \InvalidArgumentException('Order does not belong to the current store.');
        }

        return DB::transaction(function () use ($order) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->status === OrderStatus::Cancelled) {
                return $order;
            }

            $order->update([
                'status' => OrderStatus::Cancelled,
                'cancelled_at' => now(),
            ]);
            $order = $order->refresh();
            $this->timeline->record($order, 'order_cancelled', 'Order cancelled.');
            OrderCancelled::dispatch($order);

            return $order;
        });
    }
}