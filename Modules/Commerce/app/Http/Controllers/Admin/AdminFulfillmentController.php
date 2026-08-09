<?php

namespace Modules\Commerce\Http\Controllers\Admin;

use App\Core\Tenancy\CurrentStore;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Commerce\Enums\Fulfillment\FulfillmentStatus;
use Modules\Commerce\Enums\Shipping\ShipmentStatus;
use Modules\Commerce\Models\Fulfillment\Fulfillment;
use Modules\Commerce\Models\Inventory\InventoryLocation;
use Modules\Commerce\Models\Order\Order;
use Modules\Commerce\Models\Shipping\Shipment;
use Modules\Commerce\Services\Fulfillment\FulfillmentManager;
use Modules\Commerce\Services\Shipping\ShippingManager;

class AdminFulfillmentController extends Controller
{
    public function index(): JsonResponse
    {
        $counts = [
            'unfulfilled' => Order::query()->where('fulfillment_status', 'unfulfilled')->count(),
            'processing' => Fulfillment::query()->whereIn('status', [FulfillmentStatus::Processing, FulfillmentStatus::Picking, FulfillmentStatus::Packing])->count(),
            'ready_to_ship' => Fulfillment::query()->where('status', FulfillmentStatus::ReadyToShip)->count(),
            'shipped' => Shipment::query()->whereIn('status', [ShipmentStatus::Created, ShipmentStatus::PickedUp])->count(),
            'in_transit' => Shipment::query()->whereIn('status', [ShipmentStatus::InTransit, ShipmentStatus::OutForDelivery])->count(),
            'delivered' => Shipment::query()->where('status', ShipmentStatus::Delivered)->count(),
            'return_requests' => \Modules\Commerce\Models\Returns\ReturnRequest::query()->whereIn('status', ['requested', 'under_review'])->count(),
        ];
        $fulfillments = Fulfillment::query()
            ->with(['order.customer', 'items.orderItem', 'location', 'shipments'])
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn (Fulfillment $fulfillment): array => $this->fulfillment($fulfillment));

        return response()->json(['data' => [
            'summary' => $counts,
            'fulfillments' => $fulfillments,
        ]]);
    }

    public function createFromOrder(Request $request, string $orderUlid, FulfillmentManager $manager): JsonResponse
    {
        $validated = $request->validate([
            'location_id' => ['nullable', 'string', 'size:26'],
            'items' => ['nullable', 'array', 'min:1'],
            'items.*.order_item_id' => ['required_with:items', 'string', 'size:26'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1', 'max:1000'],
        ]);
        $order = Order::query()->where('ulid', $orderUlid)->firstOrFail();
        $location = isset($validated['location_id'])
            ? InventoryLocation::query()->where('ulid', $validated['location_id'])->firstOrFail()
            : null;

        try {
            $fulfillment = $manager->createForOrder($order, $location, $validated['items'] ?? null);
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $this->fulfillment($fulfillment)], 201);
    }

    public function start(string $fulfillmentUlid, FulfillmentManager $manager): JsonResponse
    {
        $fulfillment = Fulfillment::query()->where('ulid', $fulfillmentUlid)->firstOrFail();

        return response()->json(['data' => $this->fulfillment($manager->start($fulfillment, request()->user()?->id))]);
    }

    public function scan(Request $request, string $fulfillmentUlid, FulfillmentManager $manager): JsonResponse
    {
        $validated = $request->validate(['barcode' => ['required', 'string', 'max:255']]);
        $fulfillment = Fulfillment::query()->where('ulid', $fulfillmentUlid)->firstOrFail();
        try {
            $fulfillment = $manager->scanBarcode($fulfillment, $validated['barcode']);
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $this->fulfillment($fulfillment)]);
    }

    public function pack(Request $request, string $fulfillmentUlid, FulfillmentManager $manager): JsonResponse
    {
        $validated = $request->validate([
            'type' => ['nullable', 'string', 'max:50'],
            'weight' => ['nullable', 'numeric', 'min:0'],
            'width' => ['nullable', 'numeric', 'min:0'],
            'height' => ['nullable', 'numeric', 'min:0'],
            'length' => ['nullable', 'numeric', 'min:0'],
        ]);
        $fulfillment = Fulfillment::query()->where('ulid', $fulfillmentUlid)->firstOrFail();

        return response()->json(['data' => $this->fulfillment($manager->pack($fulfillment, $validated))]);
    }

    public function createShipment(Request $request, string $fulfillmentUlid, ShippingManager $manager): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'max:50'],
            'service_code' => ['nullable', 'string', 'max:50'],
            'package' => ['nullable', 'array'],
        ]);
        $fulfillment = Fulfillment::query()->where('ulid', $fulfillmentUlid)->firstOrFail();
        try {
            $shipment = $manager->createShipment($fulfillment, $validated['provider'], $validated['service_code'] ?? 'standard', $validated['package'] ?? []);
        } catch (\InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json(['data' => $this->shipment($shipment)], 201);
    }

    public function updateShipment(Request $request, string $shipmentUlid, ShippingManager $manager): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'in:'.implode(',', array_map(fn (ShipmentStatus $status) => $status->value, ShipmentStatus::cases()))],
            'message' => ['nullable', 'string', 'max:255'],
            'provider_event_id' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
        ]);
        $shipment = Shipment::query()->where('ulid', $shipmentUlid)->firstOrFail();
        $status = ShipmentStatus::from($validated['status']);
        $shipment = $manager->updateStatus(
            $shipment,
            $status,
            $validated['message'] ?? match ($status) {
                ShipmentStatus::Delivered => 'Teslim edildi.',
                ShipmentStatus::OutForDelivery => 'Dağıtıma çıktı.',
                ShipmentStatus::InTransit => 'Gönderi yolda.',
                default => 'Gönderi durumu güncellendi.',
            },
            $validated['provider_event_id'] ?? null,
            $validated['location'] ?? null,
        );

        return response()->json(['data' => $this->shipment($shipment)]);
    }

    private function fulfillment(Fulfillment $fulfillment): array
    {
        $fulfillment->loadMissing(['order.customer', 'items.orderItem.variant', 'location', 'shipments']);

        return [
            'id' => $fulfillment->ulid,
            'status' => $fulfillment->status->value,
            'order' => [
                'id' => $fulfillment->order->ulid,
                'number' => $fulfillment->order->order_number,
                'customer' => $fulfillment->order->customer_email,
            ],
            'location' => $fulfillment->location === null ? null : [
                'id' => $fulfillment->location->ulid,
                'name' => $fulfillment->location->name,
                'code' => $fulfillment->location->code,
            ],
            'package' => $fulfillment->package,
            'started_at' => $fulfillment->started_at?->toIso8601String(),
            'packed_at' => $fulfillment->packed_at?->toIso8601String(),
            'fulfilled_at' => $fulfillment->fulfilled_at?->toIso8601String(),
            'items' => $fulfillment->items->map(fn ($item): array => [
                'id' => $item->ulid,
                'order_item_id' => $item->orderItem->ulid,
                'title' => $item->orderItem->product_title,
                'variant_title' => $item->orderItem->variant_title,
                'sku' => $item->orderItem->sku,
                'barcode' => $item->orderItem->variant?->barcode,
                'quantity' => $item->quantity,
                'picked_quantity' => $item->picked_quantity,
                'status' => $item->status->value,
            ])->values()->all(),
            'shipments' => $fulfillment->shipments->map(fn (Shipment $shipment): array => $this->shipment($shipment))->values()->all(),
        ];
    }

    private function shipment(Shipment $shipment): array
    {
        $shipment->loadMissing('events');

        return [
            'id' => $shipment->ulid,
            'provider' => $shipment->provider,
            'tracking_number' => $shipment->tracking_number,
            'tracking_url' => $shipment->tracking_url,
            'status' => $shipment->status->value,
            'service_code' => $shipment->service_code,
            'package_weight' => $shipment->package_weight,
            'package_dimensions' => $shipment->package_dimensions,
            'shipped_at' => $shipment->shipped_at?->toIso8601String(),
            'delivered_at' => $shipment->delivered_at?->toIso8601String(),
            'events' => $shipment->events->map(fn ($event): array => [
                'id' => $event->ulid,
                'status' => $event->normalized_status->value,
                'location' => $event->location,
                'message' => $event->message,
                'occurred_at' => $event->occurred_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
