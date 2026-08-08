<?php

namespace Modules\Commerce\ValueObjects;

use Modules\Commerce\Models\Discount\Discount;

final readonly class DiscountApplication
{
    /**
     * @param  array<int, Money>  $itemDiscounts
     */
    public function __construct(
        public Discount $discount,
        public array $itemDiscounts,
        public Money $itemDiscountTotal,
        public bool $grantsFreeShipping,
    ) {}
}