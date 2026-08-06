<?php

namespace Modules\Commerce\Services\Inventory;

use App\Core\Tenancy\CurrentStore;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Exceptions\Inventory\CrossStoreInventoryException;
use Modules\Commerce\Exceptions\Inventory\InvalidInventoryAdjustmentException;
use Modules\Commerce\Models\Catalog\ProductVariant;
use Modules\Commerce\Models\Inventory\InventoryItem;
use Modules\Commerce\Models\Inventory\InventoryLevel;
use Modules\Commerce\Models\Inventory\InventoryLocation;

class InventoryManager
{
    public function __construct(private readonly CurrentStore $currentStore) {}

    public function setTracking(ProductVariant $variant, bool $isTracked, bool $allowOversell = false): InventoryItem
    {
        $this->assertVariantBelongsToCurrentStore($variant);

        return DB::transaction(function () use ($variant, $isTracked, $allowOversell) {
            $item = InventoryItem::query()
                ->where('product_variant_id', $variant->id)
                ->lockForUpdate()
                ->first();
            if ($item === null) {
                return InventoryItem::query()->create([
                    'product_variant_id' => $variant->id,
                    'is_tracked' => $isTracked,
                    'allow_oversell' => $allowOversell,
                ]);
            }

            $item->update(['is_tracked' => $isTracked, 'allow_oversell' => $allowOversell]);

            return $item->refresh();
        });
    }

    public function setAvailable(
        ProductVariant $variant,
        InventoryLocation $location,
        int $quantity,
        string $reason = 'product_editor',
    ): InventoryLevel {
        $this->assertVariantBelongsToCurrentStore($variant);
        $this->assertLocationBelongsToCurrentStore($location);
        if ($quantity < 0) {
            throw new InvalidInventoryAdjustmentException('Inventory quantity cannot be negative.');
        }

        return DB::transaction(function () use ($variant, $location, $quantity, $reason) {
            $item = $this->setTracking($variant, true);
            $level = InventoryLevel::query()
                ->where('inventory_item_id', $item->id)
                ->where('inventory_location_id', $location->id)
                ->lockForUpdate()
                ->first();
            $before = $level?->available_quantity ?? 0;
            if ($level === null) {
                $level = InventoryLevel::query()->create([
                    'inventory_item_id' => $item->id,
                    'inventory_location_id' => $location->id,
                    'available_quantity' => $quantity,
                ]);
            } else {
                if ($quantity < $level->reserved_quantity) {
                    throw new InvalidInventoryAdjustmentException('Available stock cannot be lower than reserved stock.');
                }
                $level->update(['available_quantity' => $quantity]);
            }

            if ($before !== $quantity) {
                $item->movements()->create([
                    'inventory_location_id' => $location->id,
                    'type' => 'adjustment',
                    'quantity_delta' => $quantity - $before,
                    'quantity_before' => $before,
                    'quantity_after' => $quantity,
                    'reason' => $reason,
                    'created_by' => auth()->id(),
                ]);
            }

            return $level->refresh();
        });
    }

    private function assertVariantBelongsToCurrentStore(ProductVariant $variant): void
    {
        if ($variant->store_id !== $this->currentStore->id()) {
            throw new CrossStoreInventoryException('Variant does not belong to the current store.');
        }
    }

    private function assertLocationBelongsToCurrentStore(InventoryLocation $location): void
    {
        if ($location->store_id !== $this->currentStore->id()) {
            throw new CrossStoreInventoryException('Inventory location does not belong to the current store.');
        }
    }
}