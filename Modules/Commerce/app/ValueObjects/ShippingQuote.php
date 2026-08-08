<?php

namespace Modules\Commerce\ValueObjects;

use Modules\Commerce\Models\Shipping\ShippingMethod;

final readonly class ShippingQuote
{
    public function __construct(
        public ShippingMethod $method,
        public Money $amount,
    ) {}
}