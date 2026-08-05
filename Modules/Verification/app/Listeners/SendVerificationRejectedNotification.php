<?php

namespace Modules\Verification\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Verification\Events\VerificationRejected;
use Modules\Verification\Notifications\VerificationRejectedNotification;

class SendVerificationRejectedNotification implements ShouldQueue
{
    public function handle(VerificationRejected $event): void
    {
        $event->verificationRequest->merchant->owner
            ->notify(new VerificationRejectedNotification($event->verificationRequest));
    }
}
