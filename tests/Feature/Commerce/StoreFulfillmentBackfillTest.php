<?php

namespace Tests\Feature\Commerce;

use App\Core\Tenancy\CurrentStore;
use App\Core\Tenancy\Scopes\StoreScope;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Commerce\Models\Inventory\InventoryLocation;
use Modules\Commerce\Models\Shipping\ShippingMethod;
use Modules\Commerce\Models\Shipping\ShippingZone;
use Modules\Commerce\Models\Shipping\ShippingZoneRegion;
use Modules\Commerce\Models\Tax\TaxRate;
use Modules\Commerce\Services\Fulfillment\StoreFulfillmentProvisioner;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Models\Store;
use Tests\TestCase;

class StoreFulfillmentBackfillTest extends TestCase
{
    use DatabaseTransactions;

    public function test_existing_store_defaults_can_be_backfilled_idempotently_without_leaking_context(): void
    {
        $previousStore = $this->createStore('Previous Context Store');
        $existingStore = $this->createStore('Existing Backfill Store');
        app(CurrentStore::class)->set($previousStore);
        $provisioner = app(StoreFulfillmentProvisioner::class);

        $provisioner->provision($existingStore);
        $provisioner->provision($existingStore);

        $this->assertSame($previousStore->id, app(CurrentStore::class)->id());
        $this->assertSame(1, $this->countForStore(InventoryLocation::class, $existingStore, ['code' => 'MAIN']));
        $this->assertSame(1, $this->countForStore(ShippingZone::class, $existingStore, ['name' => 'Türkiye']));
        $this->assertSame(1, $this->countForStore(ShippingZoneRegion::class, $existingStore, ['country_code' => 'TR']));
        $this->assertSame(1, $this->countForStore(ShippingMethod::class, $existingStore, ['name' => 'Standart Kargo']));
        $this->assertSame(1, $this->countForStore(TaxRate::class, $existingStore, ['country_code' => 'TR']));
    }

    /** @param array<string, mixed> $attributes */
    private function countForStore(string $model, Store $store, array $attributes): int
    {
        return $model::withoutGlobalScope(StoreScope::class)
            ->where('store_id', $store->id)
            ->where($attributes)
            ->count();
    }

    private function createStore(string $name): Store
    {
        $user = User::factory()->create();
        $merchant = Merchant::create(['owner_user_id' => $user->id]);

        return $merchant->stores()->create([
            'name' => $name,
            'slug' => str($name)->slug().'-'.str()->random(8),
        ]);
    }
}
