<?php

namespace Modules\Commerce\Listeners;

use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Modules\Commerce\Events\Customer\CustomerCreated;
use Modules\Commerce\Events\Inventory\InventoryAdjusted;
use Modules\Commerce\Events\Inventory\InventoryReservationReleased;
use Modules\Commerce\Events\Inventory\InventoryReserved;
use Modules\Commerce\Events\Order\OrderCancelled;
use Modules\Commerce\Events\Order\OrderPlaced;
use Modules\Commerce\Events\Payment\PaymentFailed;
use Modules\Commerce\Events\Payment\PaymentRefunded;
use Modules\Commerce\Events\Payment\PaymentSucceeded;
use Modules\Commerce\Services\Dashboard\MerchantDashboardCache;

class InvalidateMerchantDashboardCache implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly MerchantDashboardCache $cache) {}

    public function handle(object $event): void
    {
        $storeId = match (true) {
            $event instanceof OrderPlaced,
            $event instanceof OrderCancelled => $event->order->store_id,
            $event instanceof PaymentSucceeded,
            $event instanceof PaymentFailed,
            $event instanceof PaymentRefunded => $event->payment->store_id,
            $event instanceof CustomerCreated => $event->customer->store_id,
            $event instanceof InventoryAdjusted => $event->level->store_id,
            $event instanceof InventoryReserved,
            $event instanceof InventoryReservationReleased => $event->reservation->store_id,
            default => null,
        };

        if ($storeId !== null) {
            $this->cache->forgetStore((int) $storeId);
        }
    }
}
