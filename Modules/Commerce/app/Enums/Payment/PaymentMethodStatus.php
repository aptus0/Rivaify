<?php

namespace Modules\Commerce\Enums\Payment;

enum PaymentMethodStatus: string
{
    case Active = 'active';
    case Expired = 'expired';
    case Revoked = 'revoked';
}
