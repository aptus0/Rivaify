<?php

namespace Modules\Commerce\Services\Returns;

use Illuminate\Support\Facades\DB;
use Modules\Commerce\Enums\Order\FulfillmentStatus as OrderFulfillmentStatus;
use Modules\Commerce\Enums\Returns\ReturnStatus;
use Modules\Commerce\Models\Inventory\InventoryItem;
use Modules\Commerce\Models\Inventory\InventoryLevel;
use Modules\Commerce\Models\Order\Order;
use Modules\Commerce\Models\Returns\ReturnRequest;
use Modules\Commerce\Services\Order\OrderTimeline;
use Modules\Commerce\Services\Payment\RefundManager;
use Modules\Commerce\ValueObjects\Money;

class ReturnManager
{
    public function __construct(
        private readonly OrderTimeline $timeline,
        private readonly RefundManager $refunds,
    ) {}

    /**
     * @param  array<int, array{order_item_id:string, quantity:int, reason_code?:string, resolution?:string}>  $items
     */
    public function request(Order $order, array $items, ?string $reason = null, ?string $customerNote = null): ReturnRequest
    {
        return DB::transaction(function () use ($order, $items, $reason, $customerNote): ReturnRequest {
            $order = Order::query()->with(['items.returnItems'])->lockForUpdate()->findOrFail($order->id);
            $orderItems = $order->items->keyBy('ulid');
            if ($items === []) {
                throw new \InvalidArgumentException('İade için en az bir ürün seçilmeli.');
            }
            $return = ReturnRequest::query()->create([
                'order_id' => $order->id,
                'customer_id' => $order->customer_id,
                'return_number' => $this->nextNumber(),
                'status' => ReturnStatus::Requested,
                'reason' => $reason,
                'customer_note' => $customerNote,
                'requested_at' => now(),
            ]);
            foreach ($items as $line) {
                $orderItem = $orderItems->get($line['order_item_id']);
                $quantity = (int) $line['quantity'];
                if ($orderItem === null || $quantity < 1) {
                    throw new \InvalidArgumentException('İade ürünü siparişe ait değil.');
                }
                $alreadyReturned = $orderItem->returnItems->sum('quantity');
                if ($alreadyReturned + $quantity > $orderItem->quantity) {
                    throw new \InvalidArgumentException('İade miktarı satın alınan miktarı aşamaz.');
                }
                $return->items()->create([
                    'order_item_id' => $orderItem->id,
                    'quantity' => $quantity,
                    'reason_code' => $line['reason_code'] ?? 'other',
                    'resolution' => $line['resolution'] ?? 'refund',
                ]);
            }
            $this->timeline->record($order, 'return.requested', 'İade talebi oluşturuldu.', metadata: [
                'return_id' => $return->ulid,
            ]);

            return $return->load(['order.customer', 'items.orderItem', 'refunds']);
        });
    }

    public function approve(ReturnRequest $return, ?string $internalNote = null): ReturnRequest
    {
        return DB::transaction(function () use ($return, $internalNote): ReturnRequest {
            $return = ReturnRequest::query()->with('order')->lockForUpdate()->findOrFail($return->id);
            $return->update([
                'status' => ReturnStatus::ReturnShipping,
                'internal_note' => $internalNote,
                'approved_at' => now(),
                'return_tracking_number' => $return->return_tracking_number ?? 'RV-RET-'.str($return->ulid)->substr(-10)->upper(),
            ]);
            $this->timeline->record($return->order, 'return.approved', 'İade talebi onaylandı.', metadata: [
                'return_id' => $return->ulid,
            ]);

            return $return->refresh()->load(['order.customer', 'items.orderItem', 'refunds']);
        });
    }

    public function receive(ReturnRequest $return): ReturnRequest
    {
        return DB::transaction(function () use ($return): ReturnRequest {
            $return = ReturnRequest::query()->with('order')->lockForUpdate()->findOrFail($return->id);
            $return->update([
                'status' => ReturnStatus::Inspection,
                'received_at' => now(),
            ]);
            $this->timeline->record($return->order, 'return.received', 'İade depoya ulaştı.', metadata: [
                'return_id' => $return->ulid,
            ]);

            return $return->refresh()->load(['order.customer', 'items.orderItem', 'refunds']);
        });
    }

    /**
     * @param  array<int, array{return_item_id:string, condition:string, restock:bool}>  $items
     */
    public function inspect(ReturnRequest $return, array $items, ?int $locationId = null): ReturnRequest
    {
        return DB::transaction(function () use ($return, $items, $locationId): ReturnRequest {
            $return = ReturnRequest::query()->with(['order', 'items.orderItem.variant'])->lockForUpdate()->findOrFail($return->id);
            $byId = collect($items)->keyBy('return_item_id');
            foreach ($return->items as $item) {
                $inspection = $byId->get($item->ulid, []);
                $restock = (bool) ($inspection['restock'] ?? false);
                $item->update([
                    'condition' => $inspection['condition'] ?? 'opened',
                    'restock' => $restock,
                ]);
                if ($restock && $item->orderItem->variant_id !== null) {
                    $inventory = InventoryItem::query()->where('product_variant_id', $item->orderItem->variant_id)->lockForUpdate()->first();
                    if ($inventory !== null) {
                        $level = InventoryLevel::query()
                            ->where('inventory_item_id', $inventory->id)
                            ->when($locationId !== null, fn ($query) => $query->where('inventory_location_id', $locationId))
                            ->orderBy('id')
                            ->lockForUpdate()
                            ->first();
                        if ($level !== null) {
                            $before = $level->available_quantity;
                            $level->increment('available_quantity', $item->quantity);
                            $inventory->movements()->create([
                                'inventory_location_id' => $level->inventory_location_id,
                                'type' => 'return',
                                'quantity_delta' => $item->quantity,
                                'quantity_before' => $before,
                                'quantity_after' => $before + $item->quantity,
                                'reason' => 'return_received',
                                'reference_type' => ReturnRequest::class,
                                'reference_id' => $return->id,
                                'created_by' => auth()->id(),
                            ]);
                        }
                    }
                }
            }
            $return->update(['status' => ReturnStatus::RefundPending]);
            $return->order->update(['fulfillment_status' => OrderFulfillmentStatus::Returned]);
            $this->timeline->record($return->order, 'return.inspected', 'İade incelemesi tamamlandı.', metadata: [
                'return_id' => $return->ulid,
            ]);

            return $return->refresh()->load(['order.customer', 'items.orderItem', 'refunds']);
        });
    }

    public function refund(ReturnRequest $return, Money $amount, string $idempotencyKey, ?int $userId = null)
    {
        return $this->refunds->refund($return->order, $amount, $idempotencyKey, $return, userId: $userId, reason: 'return_refund');
    }

    private function nextNumber(): string
    {
        return 'RET-'.now()->format('ymd').'-'.str()->upper(str()->random(5));
    }
}
