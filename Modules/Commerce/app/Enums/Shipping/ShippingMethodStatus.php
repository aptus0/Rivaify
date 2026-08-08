<?php

namespace Modules\Commerce\Enums\Shipping;

enum ShippingMethodStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}