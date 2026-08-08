<?php

namespace Modules\Commerce\Enums\Discount;

enum DiscountConditionType: string
{
    case CartTotal = 'cart_total';
    case Products = 'products';
    case Collections = 'collections';
}