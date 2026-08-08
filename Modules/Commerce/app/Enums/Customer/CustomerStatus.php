<?php

namespace Modules\Commerce\Enums\Customer;

enum CustomerStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
    case Blocked = 'blocked';
}