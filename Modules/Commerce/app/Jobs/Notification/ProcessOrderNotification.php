<?php

namespace Modules\Commerce\Jobs\Notification;

use App\Core\Tenancy\CurrentStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Modules\Commerce\Mail\MerchantNewOrderMail;
use Modules\Commerce\Mail\OrderCancelledMail;
use Modules\Commerce\Mail\OrderConfirmationMail;
use Modules\Commerce\Mail\PaymentConfirmationMail;
use Modules\Commerce\Mail\RefundConfirmationMail;
use Modules\Commerce\Models\Order\Order;
use Modules\Commerce\Models\Order\OrderNotificationOutbox;
use Modules\Store\Models\Store;

class ProcessOrderNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $outboxId)
    {
        $this->onQueue('notifications');
    }

    public function handle(CurrentStore $currentStore): void
    {
        $outbox = DB::transaction(function () {
            $outbox = OrderNotificationOutbox::query()->lockForUpdate()->find($this->outboxId);
            if ($outbox === null || $outbox->status !== 'pending') {
                return null;
            }

            $outbox->update([
                'status' => 'processing',
                'attempts' => $outbox->attempts + 1,
            ]);

            return $outbox->refresh();
        });
        if ($outbox === null) {
            return;
        }

        $store = Store::query()->findOrFail($outbox->store_id);
        $currentStore->set($store);

        try {
            $order = Order::query()->with(['store.merchant.owner', 'items'])->findOrFail($outbox->order_id);
            match ($outbox->type) {
                'customer_order_confirmation' => $order->customer_email === null
                    ? null
                    : Mail::to($order->customer_email)->send(new OrderConfirmationMail($order)),
                'customer_order_cancelled' => $order->customer_email === null
                    ? null
                    : Mail::to($order->customer_email)->send(new OrderCancelledMail($order)),
                'customer_payment_confirmation' => $order->customer_email === null
                    ? null
                    : Mail::to($order->customer_email)->send(new PaymentConfirmationMail($order)),
                'customer_refund_confirmation' => $order->customer_email === null
                    ? null
                    : Mail::to($order->customer_email)->send(new RefundConfirmationMail($order)),
                'merchant_new_order' => $order->store?->merchant?->owner?->email === null
                    ? null
                    : Mail::to($order->store->merchant->owner->email)->send(new MerchantNewOrderMail($order)),
                default => throw new \RuntimeException("Unknown order notification type [{$outbox->type}]."),
            };
            $outbox->update(['status' => 'sent', 'sent_at' => now(), 'last_error' => null]);
        } catch (\Throwable $exception) {
            $outbox->update(['status' => 'pending', 'last_error' => $exception->getMessage()]);

            throw $exception;
        } finally {
            $currentStore->clear();
        }
    }
}