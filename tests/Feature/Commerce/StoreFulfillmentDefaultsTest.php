<?php

namespace Tests\Feature\Commerce;

use App\Core\Tenancy\Scopes\StoreScope;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Commerce\Enums\Shipping\ShippingMethodStatus;
use Modules\Commerce\Enums\Shipping\ShippingMethodType;
use Modules\Commerce\Enums\Tax\TaxRateStatus;
use Modules\Commerce\Models\Inventory\InventoryLocation;
use Modules\Commerce\Models\Shipping\ShippingMethod;
use Modules\Commerce\Models\Shipping\ShippingZone;
use Modules\Commerce\Models\Shipping\ShippingZoneRegion;
use Modules\Commerce\Models\Tax\TaxRate;
use Modules\Store\Actions\CreateStore;
use Modules\Store\DTOs\CreateStoreData;
use Modules\Store\Events\StoreCreated;
use Tests\TestCase;

class StoreFulfillmentDefaultsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_new_store_is_provisioned_with_usable_fulfillment_defaults_idempotently(): void
    {
        $store = app(CreateStore::class)->handle(
            User::factory()->create(),
            new CreateStoreData(name: 'Yeni Hazır Mağaza'),
        );

        $location = InventoryLocation::withoutGlobalScope(StoreScope::class)
            ->where('store_id', $store->id)
            ->where('code', 'MAIN')
            ->firstOrFail();
        $this->assertSame('Ana Stok Lokasyonu', $location->name);
        $this->assertTrue($location->is_active);
        $this->assertTrue($location->inventory_enabled);
        $this->assertTrue($location->fulfillment_enabled);

        $zone = ShippingZone::withoutGlobalScope(StoreScope::class)
            ->where('store_id', $store->id)
            ->where('name', 'Türkiye')
            ->firstOrFail();
        $this->assertDatabaseHas('shipping_zone_regions', [
            'store_id' => $store->id,
            'shipping_zone_id' => $zone->id,
            'country_code' => 'TR',
            'province' => null,
        ]);
        $method = ShippingMethod::withoutGlobalScope(StoreScope::class)
            ->where('store_id', $store->id)
            ->where('shipping_zone_id', $zone->id)
            ->firstOrFail();
        $this->assertSame('Standart Kargo', $method->name);
        $this->assertSame(ShippingMethodType::FlatRate, $method->type);
        $this->assertSame(ShippingMethodStatus::Active, $method->status);
        $this->assertSame('0.00', $method->price);
        $this->assertSame(2, $method->estimated_days_min);
        $this->assertSame(5, $method->estimated_days_max);

        $tax = TaxRate::withoutGlobalScope(StoreScope::class)
            ->where('store_id', $store->id)
            ->where('country_code', 'TR')
            ->firstOrFail();
        $this->assertSame('20.00', $tax->rate);
        $this->assertTrue($tax->is_inclusive);
        $this->assertSame(TaxRateStatus::Active, $tax->status);

        StoreCreated::dispatch($store);

        $this->assertSame(1, InventoryLocation::withoutGlobalScope(StoreScope::class)->where('store_id', $store->id)->where('code', 'MAIN')->count());
        $this->assertSame(1, ShippingZone::withoutGlobalScope(StoreScope::class)->where('store_id', $store->id)->where('name', 'Türkiye')->count());
        $this->assertSame(1, ShippingZoneRegion::withoutGlobalScope(StoreScope::class)->where('store_id', $store->id)->where('shipping_zone_id', $zone->id)->count());
        $this->assertSame(1, ShippingMethod::withoutGlobalScope(StoreScope::class)->where('store_id', $store->id)->where('name', 'Standart Kargo')->count());
        $this->assertSame(1, TaxRate::withoutGlobalScope(StoreScope::class)->where('store_id', $store->id)->where('country_code', 'TR')->count());
    }
}
