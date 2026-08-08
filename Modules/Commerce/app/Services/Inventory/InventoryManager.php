<?php

namespace Modules\Commerce\Services\Inventory;

use App\Core\Tenancy\CurrentStore;
use App\Core\Tenancy\Scopes\StoreScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Enums\Inventory\InventoryReservationStatus;
use Modules\Commerce\Events\Inventory\InventoryReservationReleased;
use Modules\Commerce\Events\Inventory\InventoryReserved;
use Modules\Commerce\Exceptions\Inventory\CrossStoreInventoryException;
use Modules\Commerce\Exceptions\Inventory\InsufficientInventoryException;
use Modules\Commerce\Exceptions\Inventory\InvalidInventoryAdjustmentException;
use Modules\Commerce\Exceptions\Inventory\InvalidInventoryQuantityException;
use Modules\Commerce\Models\Catalog\ProductVariant;
use Modules\Commerce\Models\Checkout\CheckoutSession;
use Modules\Commerce\Models\Inventory\InventoryItem;
use Modules\Commerce\Models\Inventory\InventoryLevel;
use Modules\Commerce\Models\Inventory\InventoryLocation;
use Modules\Commerce\Models\Inventory\InventoryReservation;

class InventoryManager
{
    public function __construct(private readonly CurrentStore $currentStore) {}

    public function createLocation(string $name, ?string $code = null): InventoryLocation
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Inventory location name is required.');
        }

        return InventoryLocation::query()->create([
            'name' => $name,
            'code' => $code === null || trim($code) === '' ? null : mb_strtoupper(trim($code)),
        ]);
    }

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

    /**
     * Hold stock for a checkout in progress (brief: reservations expire and
     * release automatically — see releaseExpired() — so an abandoned
     * checkout doesn't lock stock forever).
     *
     * @return Collection<int, InventoryReservation>
     */
    public function reserveForCheckout(CheckoutSession $checkout, int $ttlMinutes = 15): Collection
    {
        return DB::transaction(function () use ($checkout, $ttlMinutes) {
            $checkout = $this->lockCheckout($checkout);
            $cart = $checkout->cart()->with('items')->first();
            if ($cart === null || $cart->items->isEmpty()) {
                throw new InsufficientInventoryException('Checkout cart has no items to reserve.');
            }

            $quantities = $cart->items
                ->groupBy('variant_id')
                ->map(fn (Collection $items): int => $items->sum('quantity'))
                ->all();

            return $this->reserveLocked($checkout, $quantities, $ttlMinutes);
        });
    }

    /**
     * @param  array<int, int>  $variantQuantities
     * @return Collection<int, InventoryReservation>
     */
    public function reserve(CheckoutSession $checkout, array $variantQuantities, int $ttlMinutes = 15): Collection
    {
        return DB::transaction(fn () => $this->reserveLocked($this->lockCheckout($checkout), $variantQuantities, $ttlMinutes));
    }

    /**
     * @return Collection<int, InventoryReservation>
     */
    public function extendForCheckout(CheckoutSession $checkout, int $ttlMinutes = 15): Collection
    {
        $this->assertPositiveTtl($ttlMinutes);

        return DB::transaction(function () use ($checkout, $ttlMinutes) {
            $checkout = $this->lockCheckout($checkout);
            $reservations = InventoryReservation::query()
                ->where('checkout_id', $checkout->id)
                ->where('status', InventoryReservationStatus::Active->value)
                ->lockForUpdate()
                ->get();

            foreach ($reservations as $reservation) {
                $reservation->update(['expires_at' => now()->addMinutes($ttlMinutes)]);
            }

            return $reservations->map->refresh();
        });
    }

    /**
     * @return Collection<int, InventoryReservation>
     */
    public function releaseForCheckout(CheckoutSession $checkout): Collection
    {
        return DB::transaction(function () use ($checkout) {
            $checkout = $this->lockCheckout($checkout);
            $reservations = InventoryReservation::query()
                ->where('checkout_id', $checkout->id)
                ->where('status', InventoryReservationStatus::Active->value)
                ->orderBy('inventory_item_id')
                ->lockForUpdate()
                ->get();

            foreach ($reservations as $reservation) {
                $this->releaseLocked($reservation, InventoryReservationStatus::Released);
            }

            return $reservations->map->refresh();
        });
    }

    /**
     * Called once an order is actually placed — converts a held reservation
     * into a permanent stock decrement (mirrors setAvailable's ledger
     * intent, but reservations don't go through the movements ledger since
     * they're pre-committed holds, not merchant-initiated adjustments).
     *
     * @return Collection<int, InventoryReservation>
     */
    public function commitForCheckout(CheckoutSession $checkout): Collection
    {
        return DB::transaction(function () use ($checkout) {
            $checkout = $this->lockCheckout($checkout);
            $reservations = InventoryReservation::query()
                ->where('checkout_id', $checkout->id)
                ->where('status', InventoryReservationStatus::Active->value)
                ->orderBy('inventory_item_id')
                ->lockForUpdate()
                ->get();

            foreach ($reservations as $reservation) {
                $level = InventoryLevel::query()
                    ->where('inventory_item_id', $reservation->inventory_item_id)
                    ->where('inventory_location_id', $reservation->location_id)
                    ->lockForUpdate()
                    ->first();
                if ($level === null || $level->available_quantity < $reservation->quantity || $level->reserved_quantity < $reservation->quantity) {
                    throw new InsufficientInventoryException('Inventory reservation cannot be committed because stock changed.');
                }

                $level->update([
                    'available_quantity' => $level->available_quantity - $reservation->quantity,
                    'reserved_quantity' => $level->reserved_quantity - $reservation->quantity,
                ]);
                $reservation->update([
                    'status' => InventoryReservationStatus::Committed,
                    'committed_at' => now(),
                ]);
            }

            return $reservations->map->refresh();
        });
    }

    /**
     * Scheduled hourly-or-so (see routes/console.php) to release stock held
     * by abandoned checkouts. Runs across all stores — deliberately bypasses
     * StoreScope since this is a cross-tenant maintenance sweep, not a
     * request scoped to one store's CurrentStore.
     */
    public function releaseExpired(): int
    {
        $ids = InventoryReservation::withoutGlobalScope(StoreScope::class)
            ->where('status', InventoryReservationStatus::Active->value)
            ->where('expires_at', '<=', now())
            ->orderBy('id')
            ->pluck('id');
        $released = 0;

        foreach ($ids as $id) {
            $wasReleased = DB::transaction(function () use ($id): bool {
                $reservation = InventoryReservation::withoutGlobalScope(StoreScope::class)
                    ->lockForUpdate()
                    ->find($id);
                if (
                    $reservation === null
                    || $reservation->status !== InventoryReservationStatus::Active
                    || $reservation->expires_at->isFuture()
                ) {
                    return false;
                }

                $this->releaseLocked($reservation, InventoryReservationStatus::Expired, true);

                return true;
            });
            $released += $wasReleased ? 1 : 0;
        }

        return $released;
    }

    public function sellableQuantity(ProductVariant $variant): int
    {
        $this->assertVariantBelongsToCurrentStore($variant);
        $item = InventoryItem::query()->where('product_variant_id', $variant->id)->first();
        if ($item === null || ! $item->is_tracked) {
            return PHP_INT_MAX;
        }

        return InventoryLevel::query()
            ->where('inventory_item_id', $item->id)
            ->get()
            ->sum(fn (InventoryLevel $level): int => $level->sellableQuantity());
    }

    /**
     * @param  array<int, int>  $variantQuantities
     * @return Collection<int, InventoryReservation>
     */
    private function reserveLocked(CheckoutSession $checkout, array $variantQuantities, int $ttlMinutes): Collection
    {
        $this->assertPositiveTtl($ttlMinutes);
        if ($variantQuantities === []) {
            return new Collection;
        }

        ksort($variantQuantities, SORT_NUMERIC);
        foreach ($variantQuantities as $quantity) {
            if (! is_int($quantity) || $quantity < 1) {
                throw new InvalidInventoryQuantityException('Reservation quantities must be positive integers.');
            }
        }

        $items = InventoryItem::query()
            ->whereIn('product_variant_id', array_keys($variantQuantities))
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        if ($items->count() !== count($variantQuantities)) {
            throw new InsufficientInventoryException('One or more variants are not stocked.');
        }

        $reservationsByItem = InventoryReservation::query()
            ->where('checkout_id', $checkout->id)
            ->whereIn('inventory_item_id', $items->modelKeys())
            ->orderBy('inventory_item_id')
            ->lockForUpdate()
            ->get()
            ->keyBy('inventory_item_id');
        $reservations = new Collection;

        foreach ($items as $item) {
            if (! $item->is_tracked) {
                continue;
            }

            $quantity = $variantQuantities[$item->product_variant_id];
            $reservation = $reservationsByItem->get($item->id);
            if ($reservation?->status === InventoryReservationStatus::Committed) {
                throw new InsufficientInventoryException('Committed inventory cannot be reserved again.');
            }
            if ($reservation?->status === InventoryReservationStatus::Active && $reservation->expires_at->isPast()) {
                $this->releaseLocked($reservation, InventoryReservationStatus::Expired);
                $reservation = $reservation->refresh();
            }

            $reservation = $this->upsertReservation($checkout, $item, $reservation, $quantity, $ttlMinutes);
            $reservations->push($reservation);
        }

        return $reservations;
    }

    private function upsertReservation(
        CheckoutSession $checkout,
        InventoryItem $item,
        ?InventoryReservation $reservation,
        int $quantity,
        int $ttlMinutes,
    ): InventoryReservation {
        if ($reservation?->status === InventoryReservationStatus::Active) {
            $level = $this->lockLevel($item, $reservation->location_id);
            $delta = $quantity - $reservation->quantity;
            if ($delta > 0 && $level->sellableQuantity() < $delta) {
                throw new InsufficientInventoryException('Insufficient sellable inventory for this variant.');
            }

            $level->update(['reserved_quantity' => $level->reserved_quantity + $delta]);
            $reservation->update([
                'quantity' => $quantity,
                'expires_at' => now()->addMinutes($ttlMinutes),
            ]);

            return $reservation->refresh();
        }

        $level = InventoryLevel::query()
            ->where('inventory_item_id', $item->id)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->first(fn (InventoryLevel $level): bool => $level->sellableQuantity() >= $quantity);
        if ($level === null) {
            throw new InsufficientInventoryException('Insufficient sellable inventory for this variant.');
        }

        $level->update(['reserved_quantity' => $level->reserved_quantity + $quantity]);
        $attributes = [
            'location_id' => $level->inventory_location_id,
            'quantity' => $quantity,
            'status' => InventoryReservationStatus::Active,
            'expires_at' => now()->addMinutes($ttlMinutes),
            'released_at' => null,
            'committed_at' => null,
        ];
        if ($reservation === null) {
            $reservation = $item->reservations()->create([
                ...$attributes,
                'checkout_id' => $checkout->id,
            ]);
        } else {
            $reservation->update($attributes);
            $reservation = $reservation->refresh();
        }

        InventoryReserved::dispatch($reservation);

        return $reservation;
    }

    private function releaseLocked(
        InventoryReservation $reservation,
        InventoryReservationStatus $status,
        bool $withoutStoreScope = false,
    ): void {
        $levelQuery = $withoutStoreScope
            ? InventoryLevel::withoutGlobalScope(StoreScope::class)
            : InventoryLevel::query();
        $level = $levelQuery
            ->where('inventory_item_id', $reservation->inventory_item_id)
            ->where('inventory_location_id', $reservation->location_id)
            ->lockForUpdate()
            ->first();
        if ($level === null || $level->reserved_quantity < $reservation->quantity) {
            throw new InsufficientInventoryException('Inventory reservation state is inconsistent.');
        }

        $level->update(['reserved_quantity' => $level->reserved_quantity - $reservation->quantity]);
        $reservation->update([
            'status' => $status,
            'released_at' => now(),
        ]);
        InventoryReservationReleased::dispatch($reservation->refresh());
    }

    private function lockLevel(InventoryItem $item, int $locationId): InventoryLevel
    {
        $level = InventoryLevel::query()
            ->where('inventory_item_id', $item->id)
            ->where('inventory_location_id', $locationId)
            ->lockForUpdate()
            ->first();
        if ($level === null) {
            throw new InsufficientInventoryException('Inventory level no longer exists.');
        }

        return $level;
    }

    private function lockCheckout(CheckoutSession $checkout): CheckoutSession
    {
        if ($checkout->store_id !== $this->currentStore->id()) {
            throw new CrossStoreInventoryException('Checkout does not belong to the current store.');
        }

        $checkout = CheckoutSession::query()->lockForUpdate()->find($checkout->id);
        if ($checkout === null) {
            throw new CrossStoreInventoryException('Checkout does not belong to the current store.');
        }

        return $checkout;
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

    private function assertPositiveTtl(int $ttlMinutes): void
    {
        if ($ttlMinutes < 1) {
            throw new InvalidInventoryQuantityException('Reservation TTL must be at least one minute.');
        }
    }
}