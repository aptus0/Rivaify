<?php

namespace Modules\Commerce\Enums\Payment;

enum PaymentAccountVerificationStatus: string
{
    case NotStarted = 'not_started';
    case Pending = 'pending';
    case Verified = 'verified';
    case Rejected = 'rejected';
}
