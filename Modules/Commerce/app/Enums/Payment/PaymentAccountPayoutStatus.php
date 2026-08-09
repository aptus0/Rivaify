<?php

namespace Modules\Commerce\Enums\Payment;

enum PaymentAccountPayoutStatus: string
{
    case Ineligible = 'ineligible';
    case Pending = 'pending';
    case Eligible = 'eligible';
    case Suspended = 'suspended';
}
