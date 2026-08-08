<?php

namespace Modules\Commerce\Listeners;

use Modules\Commerce\Events\Order\OrderPlaced;
use Modules\Commerce\Models\Order\OrderNotificationOutbox;

class SendCustomerOrderConfirmation
{
    public function handle(OrderPlaced $event): void
    {
        OrderNotificationOutbox::query()->firstOrCreate([
            'order_id' => $event->order->id,
            'type' => 'customer_order_confirmation',
        ], [
            'store_id' => $event->order->store_id,
        ]);
    }
}