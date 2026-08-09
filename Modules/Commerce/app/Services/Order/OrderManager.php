<?php

namespace Modules\Commerce\Services\Order;

use App\Core\Tenancy\CurrentStore;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Enums\Order\OrderStatus;
use Modules\Commerce\Events\Order\OrderCancelled;
use Modules\Commerce\Models\Order\Order;
use Modules\Commerce\Enums\Order\FulfillmentStatus;
use Modules\Commerce\Services\Inventory\InventoryManager;

class OrderManager
{
    public function __construct(
        private readonly CurrentStore $currentStore,
        private readonly OrderTimeline $timeline,
        private readonly InventoryManager $inventory,
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

            if ($order->fulfillment_status === FulfillmentStatus::Unfulfilled && $order->checkout !== null) {
                $this->inventory->restockCommittedForCheckout($order->checkout);
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

    public function updateFulfillment(Order $order, FulfillmentStatus $status): Order
    {
        if ($order->store_id !== $this->currentStore->id()) throw new \InvalidArgumentException('Order does not belong to the current store.');
        return DB::transaction(function () use ($order, $status) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($order->status === OrderStatus::Cancelled) throw new \InvalidArgumentException('Cancelled orders cannot be fulfilled.');
            $order->update(['fulfillment_status' => $status]);
            $this->timeline->record($order, 'fulfillment_updated', 'Fulfillment status updated to '.$status->value.'.');
            return $order->fresh()->load(['customer', 'items', 'addresses', 'taxLines', 'events', 'payments']);
        });
    }
}
