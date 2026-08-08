<?php

namespace Modules\Commerce\Events\Tax;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Commerce\Models\Cart\Cart;

class TaxApplied
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Cart $cart) {}
}