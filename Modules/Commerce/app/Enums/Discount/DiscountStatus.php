<?php

namespace Modules\Commerce\Enums\Discount;

enum DiscountStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}