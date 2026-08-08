<?php

namespace Modules\Commerce\Events\Discount;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Commerce\Models\Cart\Cart;
use Modules\Commerce\Models\Discount\Discount;

class DiscountApplied
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Cart $cart,
        public readonly Discount $discount,
    ) {}
}