<?php

namespace Modules\Commerce\Listeners;

use App\Core\Shared\Services\ActivityLogger;
use Modules\Commerce\Events\Catalog\CategoryCreated;

class LogCategoryCreatedActivity
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function handle(CategoryCreated $event): void
    {
        $this->logger->log(
            event: 'category.created',
            subject: $event->category,
            storeId: $event->category->store_id,
        );
    }
}
