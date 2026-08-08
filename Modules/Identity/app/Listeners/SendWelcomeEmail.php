<?php

namespace Modules\Identity\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Identity\Events\UserRegistered;
use Modules\Identity\Notifications\WelcomeNotification;

class SendWelcomeEmail implements ShouldQueue
{
    public function handle(UserRegistered $event): void
    {
        $event->user->notify(new WelcomeNotification());
    }
}
