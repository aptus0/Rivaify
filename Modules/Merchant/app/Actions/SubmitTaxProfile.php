<?php

namespace Modules\Merchant\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Merchant\DTOs\SubmitTaxProfileData;
use Modules\Merchant\Models\Merchant;
use Modules\Merchant\Models\TaxProfile;
use Modules\Store\Enums\OnboardingStatus;
use Modules\Store\Models\Store;

class SubmitTaxProfile
{
    public function handle(Merchant $merchant, Store $store, SubmitTaxProfileData $data): TaxProfile
    {
        return DB::transaction(function () use ($merchant, $store, $data) {
            $profile = $merchant->taxProfile()->updateOrCreate([], [
                'tax_office' => $data->taxOffice,
                'tax_number' => $data->taxNumber,
                'legal_entity_name' => $data->legalEntityName,
                'submitted_at' => now(),
            ]);

            if ($store->onboarding_status === OnboardingStatus::TaxInformation) {
                $store->update(['onboarding_status' => OnboardingStatus::Documents]);
            }

            return $profile;
        });
    }
}
