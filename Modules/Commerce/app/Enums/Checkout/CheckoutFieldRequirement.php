<?php

namespace Modules\Commerce\Enums\Checkout;

enum CheckoutFieldRequirement: string
{
    case Required = 'required';
    case Optional = 'optional';
    case Hidden = 'hidden';
}
