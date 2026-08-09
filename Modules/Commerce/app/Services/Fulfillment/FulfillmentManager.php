<?php

namespace Modules\Commerce\Services\Fulfillment;

use Illuminate\Support\Facades\DB;
use Modules\Commerce\Enums\Fulfillment\FulfillmentItemStatus;
use Modules\Commerce\Enums\Fulfillment\FulfillmentStatus;
use Modules\Commerce\Enums\Order\FulfillmentStatus as OrderFulfillmentStatus;
use Modules\Commerce\Models\Fulfillment\Fulfillment;
use Modules\Commerce\Models\Inventory\InventoryLocation;
use Modules\Commerce\Models\Order\Order;
use Modules\Commerce\Models\Order\OrderItem;
use Modules\Commerce\Services\Order\OrderTimeline;

class FulfillmentManager
{
    public function __construct(private readonly OrderTimeline $timeline) {}

    /**
     * @param  array<int, array{order_item_id:string, quantity:int}>|null  $items
     */
    public function createForOrder(Order $order, ?InventoryLocation $location = null, ?array $items = null): Fulfillment
    {
        return DB::transaction(function () use ($order, $location, $items): Fulfillment {
            $order = Order::query()->with(['items.fulfillmentItems'])->lockForUpdate()->findOrFail($order->id);
            $orderItems = $order->items->keyBy('ulid');
            $requested = $items ?? $order->items->map(fn (OrderItem $item): array => [
                'order_item_id' => $item->ulid,
                'quantity' => $item->quantity - $item->fulfillmentItems->sum('quantity'),
            ])->filter(fn (array $item): bool => $item['quantity'] > 0)->values()->all();
            if ($requested === []) {
                throw new \InvalidArgumentException('Bu sipariş için hazırlanacak ürün kalmadı.');
            }

            $fulfillment = Fulfillment::query()->create([
                'order_id' => $order->id,
                'location_id' => $location?->id,
                'status' => FulfillmentStatus::Unfulfilled,
            ]);

            foreach ($requested as $line) {
                $orderItem = $orderItems->get($line['order_item_id']);
                $quantity = (int) $line['quantity'];
                if ($orderItem === null || $quantity < 1) {
                    throw new \InvalidArgumentException('Fulfillment ürünü siparişe ait değil.');
                }
                $alreadyAllocated = $orderItem->fulfillmentItems->sum('quantity');
                if ($alreadyAllocated + $quantity > $orderItem->quantity) {
                    throw new \InvalidArgumentException('Fulfillment miktarı satın alınan miktarı aşamaz.');
                }
                $fulfillment->items()->create([
                    'order_item_id' => $orderItem->id,
                    'quantity' => $quantity,
                ]);
            }

            $order->update(['fulfillment_status' => OrderFulfillmentStatus::Partial]);
            $this->timeline->record($order, 'fulfillment.created', 'Sipariş fulfillment kuyruğuna eklendi.', metadata: [
                'fulfillment_id' => $fulfillment->ulid,
            ]);

            return $fulfillment->load(['order.customer', 'items.orderItem.variant.product', 'location', 'shipments']);
        });
    }

    public function start(Fulfillment $fulfillment, ?int $userId = null): Fulfillment
    {
        return $this->transition($fulfillment, FulfillmentStatus::Processing, [
            'started_at' => now(),
            'assigned_to' => $userId,
        ], 'fulfillment.started', 'Hazırlama başladı.');
    }

    public function scanBarcode(Fulfillment $fulfillment, string $barcode): Fulfillment
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            throw new \InvalidArgumentException('Barkod gerekli.');
        }

        return DB::transaction(function () use ($fulfillment, $barcode): Fulfillment {
            $fulfillment = Fulfillment::query()
                ->with(['items.orderItem.variant'])
                ->lockForUpdate()
                ->findOrFail($fulfillment->id);
            $item = $fulfillment->items->first(function ($item) use ($barcode): bool {
                $variant = $item->orderItem->variant;

                return $item->picked_quantity < $item->quantity
                    && $variant !== null
                    && in_array($barcode, array_filter([$variant->barcode, $variant->sku]), true);
            });
            if ($item === null) {
                throw new \InvalidArgumentException('Bu ürün siparişe ait değil.');
            }

            $nextPicked = $item->picked_quantity + 1;
            $item->update([
                'picked_quantity' => $nextPicked,
                'status' => $nextPicked >= $item->quantity ? FulfillmentItemStatus::Picked : FulfillmentItemStatus::Pending,
                'picked_at' => $nextPicked >= $item->quantity ? now() : $item->picked_at,
            ]);
            $allPicked = $fulfillment->items()->whereColumn('picked_quantity', '<', 'quantity')->doesntExist();
            $fulfillment->update([
                'status' => $allPicked ? FulfillmentStatus::Picking : FulfillmentStatus::Processing,
                'picked_at' => $allPicked ? now() : $fulfillment->picked_at,
            ]);
            $this->timeline->record($fulfillment->order, 'fulfillment.item_picked', 'Ürün barkod ile doğrulandı.', metadata: [
                'fulfillment_id' => $fulfillment->ulid,
                'barcode' => $barcode,
            ]);

            return $fulfillment->refresh()->load(['order.customer', 'items.orderItem.variant.product', 'location', 'shipments']);
        });
    }

    /**
     * @param  array{type?:string, weight?:string|float|int, width?:string|float|int, height?:string|float|int, length?:string|float|int}  $package
     */
    public function pack(Fulfillment $fulfillment, array $package): Fulfillment
    {
        return $this->transition($fulfillment, FulfillmentStatus::ReadyToShip, [
            'packed_at' => now(),
            'package' => [
                'type' => $package['type'] ?? 'custom',
                'weight' => isset($package['weight']) ? (string) $package['weight'] : null,
                'width' => isset($package['width']) ? (string) $package['width'] : null,
                'height' => isset($package['height']) ? (string) $package['height'] : null,
                'length' => isset($package['length']) ? (string) $package['length'] : null,
            ],
        ], 'fulfillment.packed', 'Sipariş paketlendi.');
    }

    public function markShipped(Fulfillment $fulfillment): Fulfillment
    {
        return $this->transition($fulfillment, FulfillmentStatus::Shipped, [
            'fulfilled_at' => now(),
        ], 'fulfillment.completed', 'Fulfillment kargoya verildi.');
    }

    private function transition(Fulfillment $fulfillment, FulfillmentStatus $status, array $attributes, string $event, string $message): Fulfillment
    {
        return DB::transaction(function () use ($fulfillment, $status, $attributes, $event, $message): Fulfillment {
            $fulfillment = Fulfillment::query()->with('order')->lockForUpdate()->findOrFail($fulfillment->id);
            $fulfillment->update(['status' => $status, ...$attributes]);
            $this->timeline->record($fulfillment->order, $event, $message, metadata: [
                'fulfillment_id' => $fulfillment->ulid,
            ]);

            return $fulfillment->refresh()->load(['order.customer', 'items.orderItem.variant.product', 'location', 'shipments']);
        });
    }
}
