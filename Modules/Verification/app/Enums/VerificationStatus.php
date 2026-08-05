<?php

namespace Modules\Verification\Enums;

enum VerificationStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case InReview = 'in_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case NeedsInformation = 'needs_information';
}
