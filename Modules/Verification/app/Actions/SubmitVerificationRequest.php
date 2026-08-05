<?php

namespace Modules\Verification\Actions;

use Illuminate\Support\Facades\DB;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Enums\OnboardingStatus;
use Modules\Store\Models\Store;
use Modules\Verification\Enums\VerificationStatus;
use Modules\Verification\Events\VerificationSubmitted;
use Modules\Verification\Models\VerificationRequest;

class SubmitVerificationRequest
{
    public function handle(Merchant $merchant, Store $store): VerificationRequest
    {
        return DB::transaction(function () use ($merchant, $store) {
            $request = VerificationRequest::query()->firstOrNew([
                'merchant_id' => $merchant->id,
                'store_id' => $store->id,
            ]);

            $request->fill([
                'status' => VerificationStatus::Pending,
                'submitted_at' => now(),
                'rejection_reason' => null,
            ])->save();

            $store->update(['onboarding_status' => OnboardingStatus::VerificationPending]);

            VerificationSubmitted::dispatch($request);

            return $request;
        });
    }
}
