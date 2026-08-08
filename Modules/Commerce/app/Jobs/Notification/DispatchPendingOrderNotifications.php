<?php

namespace Modules\Commerce\Jobs\Notification;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Commerce\Models\Order\OrderNotificationOutbox;

class DispatchPendingOrderNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct()
    {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        OrderNotificationOutbox::query()
            ->where('status', 'pending')
            ->orderBy('id')
            ->limit(100)
            ->pluck('id')
            ->each(fn (int $id) => ProcessOrderNotification::dispatch($id));
    }
}