<?php

namespace Modules\Commerce\Enums\Tax;

enum TaxRateStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}