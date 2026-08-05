<?php

namespace Modules\Merchant\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Merchant\Events\MerchantApproved;
use Modules\Merchant\Notifications\MerchantApprovedNotification;

class SendMerchantApprovedNotification implements ShouldQueue
{
    public function handle(MerchantApproved $event): void
    {
        $event->merchant->owner->notify(new MerchantApprovedNotification($event->store));
    }
}
