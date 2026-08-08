<?php

namespace Modules\Commerce\Enums\Order;

enum OrderAddressType: string
{
    case Shipping = 'shipping';
    case Billing = 'billing';
}