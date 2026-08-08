<?php

namespace Modules\Commerce\Listeners;

use Modules\Commerce\Events\Order\OrderCancelled;
use Modules\Commerce\Models\Order\OrderNotificationOutbox;

class SendCustomerOrderCancelledNotification
{
    public function handle(OrderCancelled $event): void
    {
        OrderNotificationOutbox::query()->firstOrCreate([
            'order_id' => $event->order->id,
            'type' => 'customer_order_cancelled',
        ], [
            'store_id' => $event->order->store_id,
        ]);
    }
}