<?php

namespace Modules\Commerce\Enums\Shipping;

enum ShippingMethodType: string
{
    case FlatRate = 'flat_rate';
    case FreeShipping = 'free_shipping';
}