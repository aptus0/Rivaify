<?php

namespace Modules\Commerce\Listeners;

use Modules\Commerce\Events\Payment\PaymentRefunded;
use Modules\Commerce\Models\Order\OrderNotificationOutbox;

class SendCustomerRefundConfirmation
{
    public function handle(PaymentRefunded $event): void
    {
        if ($event->payment->order_id === null) {
            return;
        }

        OrderNotificationOutbox::query()->firstOrCreate([
            'order_id' => $event->payment->order_id,
            'type' => 'customer_refund_confirmation',
        ], [
            'store_id' => $event->payment->store_id,
        ]);
    }
}