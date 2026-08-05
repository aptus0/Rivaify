<?php

namespace Modules\Verification\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\Verification\Models\VerificationRequest;

class VerificationRejected
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly VerificationRequest $verificationRequest) {}
}
