<?php

namespace Modules\Commerce\Listeners;

use Modules\Commerce\Events\Order\OrderPlaced;
use Modules\Commerce\Models\Order\OrderNotificationOutbox;

class SendMerchantNewOrderNotification
{
    public function handle(OrderPlaced $event): void
    {
        OrderNotificationOutbox::query()->firstOrCreate([
            'order_id' => $event->order->id,
            'type' => 'merchant_new_order',
        ], [
            'store_id' => $event->order->store_id,
        ]);
    }
}