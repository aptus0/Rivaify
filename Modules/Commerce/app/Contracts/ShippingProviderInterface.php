<?php

namespace Modules\Commerce\Contracts;

interface ShippingProviderInterface
{
    public function createShipment(array $shipmentData): string;
    public function trackShipment(string $trackingNumber): array;
    public function cancelShipment(string $trackingNumber): bool;
}
