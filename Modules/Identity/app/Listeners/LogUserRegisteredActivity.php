<?php

namespace Modules\Identity\Listeners;

use App\Core\Shared\Services\ActivityLogger;
use Modules\Identity\Events\UserRegistered;

class LogUserRegisteredActivity
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function handle(UserRegistered $event): void
    {
        $this->logger->log(
            event: 'user.registered',
            subject: $event->user,
            userId: $event->user->id,
        );
    }
}
