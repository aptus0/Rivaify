<?php

namespace Modules\Commerce\Listeners;

use Modules\Commerce\Services\Fulfillment\StoreFulfillmentProvisioner;
use Modules\Store\Events\StoreCreated;

class ProvisionStoreFulfillmentDefaults
{
    public function __construct(private readonly StoreFulfillmentProvisioner $provisioner) {}

    public function handle(StoreCreated $event): void
    {
        $this->provisioner->provision($event->store);
    }
}
