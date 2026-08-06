<?php

namespace Modules\Commerce\Listeners;

use App\Core\Shared\Services\ActivityLogger;
use Modules\Commerce\Events\Catalog\ProductUpdated;

class LogProductUpdatedActivity
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function handle(ProductUpdated $event): void
    {
        $this->logger->log(
            event: 'product.updated',
            subject: $event->product,
            storeId: $event->product->store_id,
        );
    }
}
