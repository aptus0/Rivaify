<?php

namespace Modules\Commerce\Http\Controllers\Admin;

use App\Core\Tenancy\CurrentStore;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Exceptions\Inventory\InvalidInventoryAdjustmentException;
use Modules\Commerce\Models\Inventory\InventoryItem;
use Modules\Commerce\Models\Inventory\InventoryLevel;
use Modules\Commerce\Models\Inventory\InventoryLocation;
use Modules\Commerce\Services\Inventory\InventoryManager;

class AdminInventoryController extends Controller
{
    private const LOW_STOCK_THRESHOLD = 5;

    public function index(Request $request, CurrentStore $currentStore): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:in_stock,low_stock,out_of_stock'],
            'location_id' => ['nullable', 'string', 'size:26'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $location = isset($validated['location_id'])
            ? InventoryLocation::query()->where('ulid', $validated['location_id'])->firstOrFail()
            : null;
        $items = $this->inventoryQuery();

        if (($search = trim((string) ($validated['q'] ?? ''))) !== '') {
            $like = '%'.mb_strtolower($search).'%';
            $items->whereHas('variant', function (Builder $variants) use ($like): void {
                $variants->where(function (Builder $variants) use ($like): void {
                    $variants
                        ->whereRaw('LOWER(title) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(sku) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(barcode) LIKE ?', [$like])
                        ->orWhereHas('product', fn (Builder $products) => $products->whereRaw('LOWER(title) LIKE ?', [$like]));
                });
            });
        }
        if ($location !== null) {
            $items->whereHas('levels', fn (Builder $levels) => $levels->where('inventory_location_id', $location->id));
        }
        if (isset($validated['status'])) {
            match ($validated['status']) {
                'out_of_stock' => $items->whereRaw($this->sellableExpression().' <= 0'),
                'low_stock' => $items->whereRaw($this->sellableExpression().' BETWEEN 1 AND '.self::LOW_STOCK_THRESHOLD),
                'in_stock' => $items->whereRaw($this->sellableExpression().' > '.self::LOW_STOCK_THRESHOLD),
            };
        }

        $items = $items
            ->orderByDesc('inventory_items.updated_at')
            ->paginate($validated['per_page'] ?? 25);

        return response()->json([
            'data' => $items->getCollection()->map(fn (InventoryItem $item): array => $this->presentItem($item))->values(),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
            ],
            'summary' => $this->summary($currentStore->id()),
            'locations' => InventoryLocation::query()
                ->where('is_active', true)
                ->where('inventory_enabled', true)
                ->orderBy('name')
                ->get()
                ->map(fn (InventoryLocation $inventoryLocation): array => $this->presentLocation($inventoryLocation))
                ->values(),
        ]);
    }

    public function adjust(
        Request $request,
        string $inventoryItemUlid,
        string $locationUlid,
        InventoryManager $inventory,
    ): JsonResponse {
        $validated = $request->validate([
            'available_quantity' => ['required', 'integer', 'min:0', 'max:2147483647'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        $item = InventoryItem::query()
            ->with('variant')
            ->where('ulid', $inventoryItemUlid)
            ->firstOrFail();
        $location = InventoryLocation::query()
            ->where('ulid', $locationUlid)
            ->where('is_active', true)
            ->where('inventory_enabled', true)
            ->firstOrFail();

        try {
            $inventory->setAvailable(
                $item->variant,
                $location,
                $validated['available_quantity'],
                trim((string) ($validated['reason'] ?? '')) ?: 'manual_adjustment',
            );
        } catch (InvalidInventoryAdjustmentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $item = $this->inventoryQuery()->where('inventory_items.id', $item->id)->firstOrFail();

        return response()->json(['data' => $this->presentItem($item)]);
    }

    private function inventoryQuery(): Builder
    {
        return InventoryItem::query()
            ->select('inventory_items.*')
            ->selectRaw($this->availableExpression().' AS aggregate_available_quantity')
            ->selectRaw($this->reservedExpression().' AS aggregate_reserved_quantity')
            ->selectRaw($this->incomingExpression().' AS aggregate_incoming_quantity')
            ->with([
                'variant.product',
                'levels' => fn ($levels) => $levels->with('location')->orderBy('inventory_location_id'),
            ])
            ->where('is_tracked', true)
            ->whereHas('variant.product');
    }

    /**
     * @return array<string, mixed>
     */
    private function presentItem(InventoryItem $item): array
    {
        $available = (int) $item->getAttribute('aggregate_available_quantity');
        $reserved = (int) $item->getAttribute('aggregate_reserved_quantity');
        $incoming = (int) $item->getAttribute('aggregate_incoming_quantity');
        $sellable = max($available - $reserved, 0);

        return [
            'id' => $item->ulid,
            'product' => [
                'id' => $item->variant->product->ulid,
                'title' => $item->variant->product->title,
            ],
            'variant' => [
                'id' => $item->variant->ulid,
                'title' => $item->variant->title,
                'sku' => $item->variant->sku,
                'barcode' => $item->variant->barcode,
            ],
            'allow_oversell' => $item->allow_oversell,
            'quantities' => [
                'available' => $available,
                'reserved' => $reserved,
                'sellable' => $sellable,
                'incoming' => $incoming,
            ],
            'status' => $this->status($sellable),
            'levels' => $item->levels->map(fn (InventoryLevel $level): array => [
                'id' => $level->ulid,
                'location' => $this->presentLocation($level->location),
                'available' => $level->available_quantity,
                'reserved' => $level->reserved_quantity,
                'sellable' => max($level->sellableQuantity(), 0),
                'incoming' => $level->incoming_quantity,
            ])->values(),
            'updated_at' => $item->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentLocation(InventoryLocation $location): array
    {
        return [
            'id' => $location->ulid,
            'name' => $location->name,
            'code' => $location->code,
            'type' => $location->type,
            'fulfillment_enabled' => $location->fulfillment_enabled,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function summary(int $storeId): array
    {
        $items = InventoryItem::query()->where('is_tracked', true)->whereHas('variant.product');
        $quantity = DB::table('inventory_levels AS levels')
            ->join('inventory_items AS items', function ($join): void {
                $join->on('items.id', '=', 'levels.inventory_item_id')
                    ->on('items.store_id', '=', 'levels.store_id');
            })
            ->where('items.store_id', $storeId)
            ->where('items.is_tracked', true)
            ->selectRaw('COALESCE(SUM(levels.available_quantity), 0) AS available')
            ->selectRaw('COALESCE(SUM(levels.reserved_quantity), 0) AS reserved')
            ->selectRaw('COALESCE(SUM(levels.incoming_quantity), 0) AS incoming')
            ->first();

        return [
            'tracked_variants' => (clone $items)->count(),
            'available' => (int) ($quantity->available ?? 0),
            'reserved' => (int) ($quantity->reserved ?? 0),
            'sellable' => max((int) ($quantity->available ?? 0) - (int) ($quantity->reserved ?? 0), 0),
            'incoming' => (int) ($quantity->incoming ?? 0),
            'low_stock' => (clone $items)->whereRaw($this->sellableExpression().' BETWEEN 1 AND '.self::LOW_STOCK_THRESHOLD)->count(),
            'out_of_stock' => (clone $items)->whereRaw($this->sellableExpression().' <= 0')->count(),
        ];
    }

    private function status(int $sellable): string
    {
        if ($sellable <= 0) {
            return 'out_of_stock';
        }

        return $sellable <= self::LOW_STOCK_THRESHOLD ? 'low_stock' : 'in_stock';
    }

    private function availableExpression(): string
    {
        return '(SELECT COALESCE(SUM(levels.available_quantity), 0) FROM inventory_levels AS levels WHERE levels.inventory_item_id = inventory_items.id AND levels.store_id = inventory_items.store_id)';
    }

    private function reservedExpression(): string
    {
        return '(SELECT COALESCE(SUM(levels.reserved_quantity), 0) FROM inventory_levels AS levels WHERE levels.inventory_item_id = inventory_items.id AND levels.store_id = inventory_items.store_id)';
    }

    private function incomingExpression(): string
    {
        return '(SELECT COALESCE(SUM(levels.incoming_quantity), 0) FROM inventory_levels AS levels WHERE levels.inventory_item_id = inventory_items.id AND levels.store_id = inventory_items.store_id)';
    }

    private function sellableExpression(): string
    {
        return '('.$this->availableExpression().' - '.$this->reservedExpression().')';
    }
}
