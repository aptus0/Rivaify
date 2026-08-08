<?php

namespace Modules\Commerce\Events\Checkout;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Commerce\Models\Checkout\CheckoutSession;

class CheckoutUpdated
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly CheckoutSession $checkout) {}
}