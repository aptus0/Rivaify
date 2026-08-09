<?php

namespace Modules\Commerce\Enums\Payment;

enum StorePaymentAccountStatus: string
{
    case PendingVerification = 'pending_verification';
    case UnderReview = 'under_review';
    case Active = 'active';
    case AttentionRequired = 'attention_required';
    case Suspended = 'suspended';
}
