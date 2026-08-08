<?php

namespace Modules\Commerce\Enums\Customer;

enum CustomerAddressType: string
{
    case Shipping = 'shipping';
    case Billing = 'billing';
}