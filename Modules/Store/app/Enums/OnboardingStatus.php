<?php

namespace Modules\Store\Enums;

/**
 * Drives the onboarding wizard (brief: state machine, not a pile of
 * booleans). Order matters — the frontend renders progress from each
 * case's position in this list.
 */
enum OnboardingStatus: string
{
    case AccountCreated = 'account_created';
    case StoreInformation = 'store_information';
    case BusinessInformation = 'business_information';
    case TaxInformation = 'tax_information';
    case Documents = 'documents';
    case VerificationPending = 'verification_pending';
    case Approved = 'approved';
    case Completed = 'completed';

    public function step(): int
    {
        return array_search($this, self::cases(), true) + 1;
    }
}
