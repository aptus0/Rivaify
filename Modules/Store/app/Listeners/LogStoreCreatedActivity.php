<?php

namespace Modules\Store\Listeners;

use App\Core\Shared\Services\ActivityLogger;
use Modules\Store\Events\StoreCreated;

class LogStoreCreatedActivity
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function handle(StoreCreated $event): void
    {
        $this->logger->log(
            event: 'store.created',
            subject: $event->store,
            storeId: $event->store->id,
        );
    }
}
