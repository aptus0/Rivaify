<?php

namespace Modules\Merchant\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Merchant\DTOs\SubmitBusinessProfileData;
use Modules\Merchant\Models\BusinessProfile;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Enums\OnboardingStatus;
use Modules\Store\Models\Store;

class SubmitBusinessProfile
{
    public function handle(Merchant $merchant, Store $store, SubmitBusinessProfileData $data): BusinessProfile
    {
        return DB::transaction(function () use ($merchant, $store, $data) {
            $profile = $merchant->businessProfile()->updateOrCreate([], [
                'legal_name' => $data->legalName,
                'trade_name' => $data->tradeName,
                'registration_number' => $data->registrationNumber,
                'contact_email' => $data->contactEmail,
                'contact_phone' => $data->contactPhone,
                'submitted_at' => now(),
            ]);

            $profile->addresses()->delete();
            foreach ($data->addresses as $address) {
                $profile->addresses()->create([
                    'type' => $address->type,
                    'line1' => $address->line1,
                    'line2' => $address->line2,
                    'city' => $address->city,
                    'state' => $address->state,
                    'postal_code' => $address->postalCode,
                    'country_code' => $address->countryCode,
                ]);
            }

            if ($store->onboarding_status === OnboardingStatus::BusinessInformation) {
                $store->update(['onboarding_status' => OnboardingStatus::TaxInformation]);
            }

            return $profile;
        });
    }
}
