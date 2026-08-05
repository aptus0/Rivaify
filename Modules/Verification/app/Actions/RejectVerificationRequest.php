<?php

namespace Modules\Verification\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Store\Enums\OnboardingStatus;
use Modules\Verification\Enums\VerificationStatus;
use Modules\Verification\Events\VerificationRejected;
use Modules\Verification\Models\VerificationRequest;

/**
 * See ApproveVerificationRequest's docblock re: scope bypass responsibility.
 */
class RejectVerificationRequest
{
    public function handle(VerificationRequest $verificationRequest, User $reviewer, string $reason): VerificationRequest
    {
        return DB::transaction(function () use ($verificationRequest, $reviewer, $reason) {
            $verificationRequest->update([
                'status' => VerificationStatus::Rejected,
                'rejection_reason' => $reason,
                'reviewed_at' => now(),
                'reviewed_by' => $reviewer->id,
            ]);

            // Send the merchant back to the documents step so they can fix
            // whatever was wrong and resubmit — not back to square one.
            $verificationRequest->store->update(['onboarding_status' => OnboardingStatus::Documents]);

            VerificationRejected::dispatch($verificationRequest);

            return $verificationRequest;
        });
    }
}
