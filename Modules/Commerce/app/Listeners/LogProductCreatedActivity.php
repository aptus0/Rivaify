<?php

namespace Modules\Commerce\Listeners;

use App\Core\Shared\Services\ActivityLogger;
use Modules\Commerce\Events\Catalog\ProductCreated;

class LogProductCreatedActivity
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function handle(ProductCreated $event): void
    {
        $this->logger->log(
            event: 'product.created',
            subject: $event->product,
            storeId: $event->product->store_id,
        );
    }
}
