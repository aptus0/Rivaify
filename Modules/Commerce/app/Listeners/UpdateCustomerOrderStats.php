<?php

namespace Modules\Commerce\Listeners;

use Illuminate\Support\Facades\DB;
use Modules\Commerce\Events\Order\OrderPlaced;
use Modules\Commerce\Models\Customer\Customer;
use Modules\Commerce\Models\Customer\CustomerEvent;
use Modules\Commerce\ValueObjects\Money;

class UpdateCustomerOrderStats
{
    public function handle(OrderPlaced $event): void
    {
        if ($event->order->customer_id === null) {
            return;
        }

        DB::transaction(function () use ($event) {
            $customer = Customer::query()->lockForUpdate()->find($event->order->customer_id);
            if ($customer === null) {
                return;
            }

            $customerEvent = CustomerEvent::query()->firstOrCreate([
                'customer_id' => $customer->id,
                'order_id' => $event->order->id,
                'type' => 'order_placed',
            ]);
            if (! $customerEvent->wasRecentlyCreated) {
                return;
            }

            $customer->update([
                'total_orders' => $customer->total_orders + 1,
                'total_spent' => Money::fromDecimal($customer->total_spent, $event->order->currency)
                    ->add(Money::fromDecimal($event->order->grand_total, $event->order->currency))
                    ->toDecimal(),
                'last_order_at' => $event->order->placed_at,
            ]);
        });
    }
}