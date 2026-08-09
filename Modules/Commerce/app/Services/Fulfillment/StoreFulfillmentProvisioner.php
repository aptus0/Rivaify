<?php

namespace Modules\Commerce\Services\Fulfillment;

use App\Core\Tenancy\CurrentStore;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Enums\Shipping\ShippingMethodStatus;
use Modules\Commerce\Enums\Shipping\ShippingMethodType;
use Modules\Commerce\Enums\Tax\TaxRateStatus;
use Modules\Commerce\Models\Inventory\InventoryLocation;
use Modules\Commerce\Models\Shipping\ShippingMethod;
use Modules\Commerce\Models\Shipping\ShippingZone;
use Modules\Commerce\Models\Tax\TaxRate;
use Modules\Store\Models\Store;

class StoreFulfillmentProvisioner
{
    public function __construct(private readonly CurrentStore $currentStore) {}

    public function provision(Store $store): void
    {
        $previousStore = $this->currentStore->has() ? $this->currentStore->store() : null;

        try {
            $this->currentStore->set($store);
            DB::transaction(fn () => $this->provisionCurrentStore($store));
        } finally {
            if ($previousStore === null) {
                $this->currentStore->clear();
            } else {
                $this->currentStore->set($previousStore);
            }
        }
    }

    private function provisionCurrentStore(Store $store): void
    {
        InventoryLocation::query()->firstOrCreate(
            ['code' => 'MAIN'],
            [
                'name' => 'Ana Stok Lokasyonu',
                'type' => 'warehouse',
                'is_active' => true,
                'fulfillment_enabled' => true,
                'inventory_enabled' => true,
            ],
        );

        $countryCode = mb_strtoupper($store->country_code);
        $zone = ShippingZone::query()->firstOrCreate([
            'name' => $countryCode === 'TR' ? 'Türkiye' : "{$countryCode} Teslimat Bölgesi",
        ]);
        $zone->regions()->firstOrCreate([
            'country_code' => $countryCode,
            'province' => null,
        ]);
        ShippingMethod::query()->firstOrCreate(
            [
                'shipping_zone_id' => $zone->id,
                'name' => 'Standart Kargo',
            ],
            [
                'type' => ShippingMethodType::FlatRate,
                'price' => '0.00',
                'estimated_days_min' => 2,
                'estimated_days_max' => 5,
                'status' => ShippingMethodStatus::Active,
            ],
        );

        $tax = $countryCode === 'TR'
            ? ['name' => 'KDV %20 (Fiyata Dahil)', 'rate' => '20.00', 'is_inclusive' => true]
            : ['name' => 'Varsayılan Vergi %0', 'rate' => '0.00', 'is_inclusive' => false];
        TaxRate::query()->firstOrCreate(
            [
                'name' => $tax['name'],
                'country_code' => $countryCode,
            ],
            [
                'rate' => $tax['rate'],
                'is_inclusive' => $tax['is_inclusive'],
                'status' => TaxRateStatus::Active,
            ],
        );
    }
}
