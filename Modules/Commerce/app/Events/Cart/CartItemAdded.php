<?php

namespace Modules\Commerce\Events\Cart;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Commerce\Models\Cart\Cart;
use Modules\Commerce\Models\Cart\CartItem;

class CartItemAdded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Cart $cart,
        public readonly CartItem $cartItem,
    ) {}
}