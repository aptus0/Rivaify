<?php

namespace Modules\Identity\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Identity\Events\UserRegistered;

/**
 * User implements MustVerifyEmail, but nothing was ever calling
 * sendEmailVerificationNotification() on registration — the "resend"
 * button on VerifyEmailPage worked (it hits Fortify's own
 * verification.send route), but the *first* email never went out.
 *
 * ShouldQueue: once MAIL_MAILER points at a real SMTP provider, this makes
 * an actual network connection — queuing it means a slow/flaky mail
 * provider can never delay or fail the registration HTTP response itself.
 */
class SendEmailVerificationNotification implements ShouldQueue
{
    public function handle(UserRegistered $event): void
    {
        if (! $event->user->hasVerifiedEmail()) {
            $event->user->sendEmailVerificationNotification();
        }
    }
}
