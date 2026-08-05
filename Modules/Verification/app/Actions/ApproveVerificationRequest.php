<?php

namespace Modules\Verification\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Merchant\Events\MerchantApproved;
use Modules\Verification\Enums\VerificationStatus;
use Modules\Verification\Models\VerificationRequest;

/**
 * Runs from Rivaify Admin, which is cross-tenant by nature — callers are
 * expected to have already fetched $verificationRequest with the
 * StoreScope deliberately bypassed (see the admin controller). This action
 * itself only mutates the already-loaded model, so it doesn't need to
 * re-query through the scope.
 */
class ApproveVerificationRequest
{
    public function handle(VerificationRequest $verificationRequest, User $reviewer): VerificationRequest
    {
        return DB::transaction(function () use ($verificationRequest, $reviewer) {
            $verificationRequest->update([
                'status' => VerificationStatus::Approved,
                'reviewed_at' => now(),
                'reviewed_by' => $reviewer->id,
            ]);

            MerchantApproved::dispatch($verificationRequest->merchant, $verificationRequest->store);

            return $verificationRequest;
        });
    }
}
