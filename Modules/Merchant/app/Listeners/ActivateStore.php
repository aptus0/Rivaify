<?php

namespace Modules\Merchant\Listeners;

use Modules\Merchant\Enums\MerchantStatus;
use Modules\Merchant\Events\MerchantApproved;
use Modules\Store\Enums\OnboardingStatus;
use Modules\Store\Enums\StoreStatus;

/**
 * Synchronous on purpose: the API response to the admin's "approve" action
 * should reflect the store's final state immediately, not an eventually-
 * consistent one a queue worker gets to later.
 */
class ActivateStore
{
    public function handle(MerchantApproved $event): void
    {
        $event->merchant->update(['status' => MerchantStatus::Active]);

        $event->store->update([
            'status' => StoreStatus::Active,
            'onboarding_status' => OnboardingStatus::Completed,
        ]);
    }
}
