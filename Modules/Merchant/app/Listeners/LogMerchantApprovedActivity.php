<?php

namespace Modules\Merchant\Listeners;

use App\Core\Shared\Services\ActivityLogger;
use Modules\Merchant\Events\MerchantApproved;

class LogMerchantApprovedActivity
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function handle(MerchantApproved $event): void
    {
        $this->logger->log(
            event: 'merchant.approved',
            subject: $event->merchant,
            storeId: $event->store->id,
        );
    }
}
