<?php

namespace Modules\Verification\Listeners;

use App\Core\Shared\Services\ActivityLogger;
use Modules\Verification\Events\VerificationSubmitted;

class LogVerificationSubmittedActivity
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function handle(VerificationSubmitted $event): void
    {
        $this->logger->log(
            event: 'verification_request.submitted',
            subject: $event->verificationRequest,
            storeId: $event->verificationRequest->store_id,
        );
    }
}
