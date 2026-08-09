<?php

namespace Modules\Commerce\Services\Shipping;

use Illuminate\Support\Facades\DB;
use Modules\Commerce\Enums\Fulfillment\FulfillmentStatus;
use Modules\Commerce\Enums\Order\FulfillmentStatus as OrderFulfillmentStatus;
use Modules\Commerce\Enums\Shipping\ShipmentStatus;
use Modules\Commerce\Models\Fulfillment\Fulfillment;
use Modules\Commerce\Models\Shipping\Shipment;
use Modules\Commerce\Services\Fulfillment\FulfillmentManager;
use Modules\Commerce\Services\Order\OrderTimeline;

class ShippingManager
{
    public function __construct(
        private readonly FulfillmentManager $fulfillments,
        private readonly OrderTimeline $timeline,
    ) {}

    /**
     * @param  array<string, mixed>  $package
     */
    public function createShipment(Fulfillment $fulfillment, string $provider, string $serviceCode = 'standard', array $package = []): Shipment
    {
        $provider = strtolower(trim($provider));
        if ($provider === '') {
            throw new \InvalidArgumentException('Kargo firması seçilmeli.');
        }

        return DB::transaction(function () use ($fulfillment, $provider, $serviceCode, $package): Shipment {
            $fulfillment = Fulfillment::query()->with('order')->lockForUpdate()->findOrFail($fulfillment->id);
            $externalReference = 'fulfillment:'.$fulfillment->ulid;
            $existing = Shipment::query()->where('external_reference', $externalReference)->first();
            if ($existing !== null) {
                return $existing->load(['fulfillment', 'order', 'events']);
            }

            $trackingNumber = 'RV-'.$provider.'-'.str($fulfillment->ulid)->substr(-10)->upper();
            $shipment = Shipment::query()->create([
                'order_id' => $fulfillment->order_id,
                'fulfillment_id' => $fulfillment->id,
                'provider' => $provider,
                'external_reference' => $externalReference,
                'provider_shipment_id' => 'mock_'.$fulfillment->ulid,
                'tracking_number' => $trackingNumber,
                'tracking_url' => url('/track/'.$trackingNumber),
                'status' => ShipmentStatus::Created,
                'service_code' => $serviceCode,
                'package_weight' => isset($package['weight']) ? (string) $package['weight'] : ($fulfillment->package['weight'] ?? null),
                'package_dimensions' => [
                    'width' => $package['width'] ?? ($fulfillment->package['width'] ?? null),
                    'height' => $package['height'] ?? ($fulfillment->package['height'] ?? null),
                    'length' => $package['length'] ?? ($fulfillment->package['length'] ?? null),
                ],
            ]);
            $this->appendEvent($shipment, ShipmentStatus::Created, 'Gönderi oluşturuldu.', 'shipment.created');
            $this->fulfillments->markShipped($fulfillment);
            $fulfillment->order->update(['fulfillment_status' => OrderFulfillmentStatus::Fulfilled]);
            $this->timeline->record($fulfillment->order, 'shipment.created', ucfirst($provider).' gönderisi oluşturuldu.', metadata: [
                'shipment_id' => $shipment->ulid,
                'tracking_number' => $trackingNumber,
            ]);

            return $shipment->load(['fulfillment', 'order', 'events']);
        });
    }

    public function updateStatus(
        Shipment $shipment,
        ShipmentStatus $status,
        string $message,
        ?string $providerEventId = null,
        ?string $location = null,
        array $payload = [],
    ): Shipment {
        return DB::transaction(function () use ($shipment, $status, $message, $providerEventId, $location, $payload): Shipment {
            $shipment = Shipment::query()->with(['order', 'fulfillment'])->lockForUpdate()->findOrFail($shipment->id);
            if ($providerEventId !== null && $shipment->events()->where('provider_event_id', $providerEventId)->exists()) {
                return $shipment->load('events');
            }

            $updates = ['status' => $status];
            if (in_array($status, [ShipmentStatus::PickedUp, ShipmentStatus::InTransit, ShipmentStatus::OutForDelivery], true) && $shipment->shipped_at === null) {
                $updates['shipped_at'] = now();
            }
            if ($status === ShipmentStatus::Delivered) {
                $updates['delivered_at'] = now();
            }
            $shipment->update($updates);
            $this->appendEvent($shipment, $status, $message, $status->value, $providerEventId, $location, $payload);

            if ($shipment->fulfillment !== null) {
                $fulfillmentStatus = match ($status) {
                    ShipmentStatus::InTransit, ShipmentStatus::PickedUp => FulfillmentStatus::InTransit,
                    ShipmentStatus::OutForDelivery => FulfillmentStatus::OutForDelivery,
                    ShipmentStatus::Delivered => FulfillmentStatus::Delivered,
                    ShipmentStatus::Returned => FulfillmentStatus::Returned,
                    ShipmentStatus::Failed => FulfillmentStatus::Failed,
                    ShipmentStatus::Cancelled => FulfillmentStatus::Cancelled,
                    default => $shipment->fulfillment->status,
                };
                $shipment->fulfillment->update([
                    'status' => $fulfillmentStatus,
                    'fulfilled_at' => $status === ShipmentStatus::Delivered ? now() : $shipment->fulfillment->fulfilled_at,
                ]);
            }
            if ($status === ShipmentStatus::Delivered) {
                $shipment->order->update(['fulfillment_status' => OrderFulfillmentStatus::Fulfilled]);
            }
            $this->timeline->record($shipment->order, 'shipment.'.$status->value, $message, metadata: [
                'shipment_id' => $shipment->ulid,
                'tracking_number' => $shipment->tracking_number,
            ]);

            return $shipment->refresh()->load(['fulfillment', 'order', 'events']);
        });
    }

    private function appendEvent(
        Shipment $shipment,
        ShipmentStatus $status,
        string $message,
        ?string $providerStatus = null,
        ?string $providerEventId = null,
        ?string $location = null,
        array $payload = [],
    ): void {
        $shipment->events()->create([
            'provider_event_id' => $providerEventId,
            'provider_status' => $providerStatus,
            'normalized_status' => $status,
            'location' => $location,
            'message' => $message,
            'occurred_at' => now(),
            'payload' => $payload === [] ? null : $payload,
        ]);
    }
}
