<?php

namespace Modules\Commerce\Events\Order;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Commerce\Models\Order\Order;

class OrderCancelled
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Order $order) {}
}