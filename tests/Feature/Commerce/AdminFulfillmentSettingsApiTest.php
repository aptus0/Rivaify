<?php

namespace Tests\Feature\Commerce;

use App\Core\Tenancy\CurrentStore;
use App\Core\Tenancy\Scopes\StoreScope;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Modules\Commerce\DTOs\Customer\CustomerAddressData;
use Modules\Commerce\DTOs\Customer\UpsertCustomerData;
use Modules\Commerce\Enums\Catalog\ProductStatus;
use Modules\Commerce\Enums\Customer\CustomerAddressType;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Models\Shipping\ShippingMethod;
use Modules\Commerce\Models\Shipping\ShippingZone;
use Modules\Commerce\Models\Shipping\ShippingZoneRegion;
use Modules\Commerce\Models\Tax\TaxRate;
use Modules\Commerce\Services\Cart\CartManager;
use Modules\Commerce\Services\Customer\CustomerManager;
use Modules\Commerce\Services\Shipping\ShippingEngine;
use Modules\Commerce\Services\Tax\TaxEngine;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Enums\StoreUserRole;
use Modules\Store\Enums\StoreUserStatus;
use Modules\Store\Models\Store;
use Modules\Store\Models\StoreUser;
use Tests\TestCase;

class AdminFulfillmentSettingsApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Referer', 'https://app.rivaify.com');
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_owner_can_manage_shipping_and_tax_with_checkout_safety_guards(): void
    {
        [$user, $store] = $this->makeStoreWithUser('Fulfillment CRUD');
        $zone = $this->makeZone($store, 'Türkiye', 'TR');
        Sanctum::actingAs($user);

        $settings = $this->forStore($store)->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.permissions.can_manage', true);
        $this->assertTrue($settings->json('data.permissions.can_manage'));

        $standard = $this->forStore($store)->postJson('/api/v1/settings/shipping-methods', [
            'name' => 'Standart Kargo',
            'type' => 'flat_rate',
            'price' => '49.90',
            'minimum_order' => '100.00',
            'maximum_order' => '5000.00',
            'estimated_days_min' => 2,
            'estimated_days_max' => 5,
            'status' => 'active',
            'shipping_zone_id' => $zone->ulid,
        ])->assertCreated()
            ->assertJsonPath('data.name', 'Standart Kargo')
            ->assertJsonPath('data.zone.id', $zone->ulid)
            ->assertJsonPath('data.price', '49.90');
        $standardId = $standard->json('data.id');

        $this->forStore($store)->postJson('/api/v1/settings/shipping-methods', [
            'name' => 'Geçersiz Aralık',
            'type' => 'flat_rate',
            'price' => '10.00',
            'minimum_order' => '500.00',
            'maximum_order' => '100.00',
        ])->assertUnprocessable()->assertJsonValidationErrors('maximum_order');

        $this->forStore($store)->patchJson("/api/v1/settings/shipping-methods/{$standardId}", ['status' => 'inactive'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('shipping_method');
        $this->forStore($store)->deleteJson("/api/v1/settings/shipping-methods/{$standardId}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('shipping_method');

        $free = $this->forStore($store)->postJson('/api/v1/settings/shipping-methods', [
            'name' => 'Ücretsiz Kargo',
            'type' => 'free_shipping',
            'price' => '999.00',
            'minimum_order' => '750.00',
            'status' => 'active',
        ])->assertCreated()->assertJsonPath('data.price', '0.00');
        $freeId = $free->json('data.id');

        $this->forStore($store)->patchJson("/api/v1/settings/shipping-methods/{$freeId}", ['price' => '123.45'])
            ->assertOk()
            ->assertJsonPath('data.type', 'free_shipping')
            ->assertJsonPath('data.price', '0.00');
        $this->forStore($store)->patchJson("/api/v1/settings/shipping-methods/{$freeId}", [
            'type' => 'flat_rate',
            'price' => '29.50',
        ])->assertOk()
            ->assertJsonPath('data.type', 'flat_rate')
            ->assertJsonPath('data.price', '29.50');
        $this->forStore($store)->patchJson("/api/v1/settings/shipping-methods/{$freeId}", ['type' => 'free_shipping'])
            ->assertOk()
            ->assertJsonPath('data.type', 'free_shipping')
            ->assertJsonPath('data.price', '0.00');

        $this->forStore($store)->patchJson("/api/v1/settings/shipping-methods/{$standardId}", ['status' => 'inactive'])
            ->assertOk()
            ->assertJsonPath('data.status', 'inactive');
        $this->forStore($store)->deleteJson("/api/v1/settings/shipping-methods/{$standardId}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);
        $this->forStore($store)->deleteJson("/api/v1/settings/shipping-methods/{$freeId}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('shipping_method');

        $this->forStore($store)->getJson('/api/v1/settings/shipping-methods')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('summary.all', 1)
            ->assertJsonPath('summary.active', 1);

        $defaultTax = $this->forStore($store)->postJson('/api/v1/settings/tax-rates', [
            'name' => 'KDV %20',
            'country_code' => 'tr',
            'rate' => '20.00',
            'is_inclusive' => true,
            'status' => 'active',
        ])->assertCreated()
            ->assertJsonPath('data.country_code', 'TR')
            ->assertJsonPath('data.applies_to_default_country', true);
        $defaultTaxId = $defaultTax->json('data.id');

        $this->forStore($store)->postJson('/api/v1/settings/tax-rates', [
            'name' => 'Geçersiz Vergi',
            'country_code' => 'TR',
            'rate' => '101.00',
            'is_inclusive' => false,
        ])->assertUnprocessable()->assertJsonValidationErrors('rate');

        $this->forStore($store)->postJson('/api/v1/settings/tax-rates', [
            'name' => 'Almanya KDV',
            'country_code' => 'DE',
            'rate' => '19.00',
            'is_inclusive' => true,
        ])->assertCreated();

        $this->forStore($store)->patchJson("/api/v1/settings/tax-rates/{$defaultTaxId}", ['status' => 'inactive'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tax_rate');
        $this->forStore($store)->deleteJson("/api/v1/settings/tax-rates/{$defaultTaxId}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tax_rate');

        $replacement = $this->forStore($store)->postJson('/api/v1/settings/tax-rates', [
            'name' => 'KDV Yedek',
            'country_code' => 'TR',
            'rate' => '10.00',
            'is_inclusive' => false,
            'status' => 'active',
        ])->assertCreated();
        $replacementId = $replacement->json('data.id');

        $this->forStore($store)->deleteJson("/api/v1/settings/tax-rates/{$defaultTaxId}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);
        $this->forStore($store)->patchJson("/api/v1/settings/tax-rates/{$replacementId}", ['country_code' => 'GB'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tax_rate');
    }

    public function test_changing_store_country_provisions_an_active_default_tax_rate_atomically(): void
    {
        [$user, $store] = $this->makeStoreWithUser('Country Change');
        Sanctum::actingAs($user);

        $this->forStore($store)->patchJson('/api/v1/settings/store', ['country_code' => 'de'])
            ->assertOk()
            ->assertJsonPath('data.store.country_code', 'DE');

        $rate = TaxRate::withoutGlobalScope(StoreScope::class)
            ->where('store_id', $store->id)
            ->where('country_code', 'DE')
            ->firstOrFail();
        $this->assertSame('0.00', $rate->rate);
        $this->assertFalse($rate->is_inclusive);
        $this->assertSame('active', $rate->status->value);

        $this->forStore($store)->getJson('/api/v1/settings/tax-rates')
            ->assertOk()
            ->assertJsonPath('default_country_code', 'DE')
            ->assertJsonPath('data.0.applies_to_default_country', true);

        $this->forStore($store)->patchJson('/api/v1/settings/store', ['country_code' => 'DE'])
            ->assertOk();
        $this->assertSame(1, TaxRate::withoutGlobalScope(StoreScope::class)
            ->where('store_id', $store->id)
            ->where('country_code', 'DE')
            ->count());
    }

    public function test_fulfillment_routes_preserve_tenant_isolation_and_role_permissions(): void
    {
        [$ownerA, $storeA] = $this->makeStoreWithUser('Tenant A');
        [, $storeB] = $this->makeStoreWithUser('Tenant B');
        app(CurrentStore::class)->set($storeB);
        $foreignMethod = ShippingMethod::query()->create([
            'name' => 'Foreign Shipping',
            'price' => '25.00',
        ]);
        $foreignTax = TaxRate::query()->create([
            'name' => 'Foreign Tax',
            'country_code' => 'TR',
            'rate' => '20.00',
        ]);

        Sanctum::actingAs($ownerA);
        $this->forStore($storeA)->getJson('/api/v1/settings/shipping-methods')
            ->assertOk()->assertJsonCount(0, 'data');
        $this->forStore($storeA)->getJson('/api/v1/settings/tax-rates')
            ->assertOk()->assertJsonCount(0, 'data');
        $this->forStore($storeA)->patchJson("/api/v1/settings/shipping-methods/{$foreignMethod->ulid}", ['name' => 'Cross Tenant'])
            ->assertNotFound();
        $this->forStore($storeA)->deleteJson("/api/v1/settings/tax-rates/{$foreignTax->ulid}")
            ->assertNotFound();

        [$staff, $staffStore] = $this->makeStoreWithUser('Staff Store', StoreUserRole::Staff);
        Sanctum::actingAs($staff);
        $this->forStore($staffStore)->getJson('/api/v1/settings')
            ->assertOk()->assertJsonPath('data.permissions.can_manage', false);
        $this->forStore($staffStore)->getJson('/api/v1/settings/shipping-methods')->assertOk();
        $this->forStore($staffStore)->postJson('/api/v1/settings/shipping-methods', $this->shippingPayload('Staff Shipping'))
            ->assertForbidden();

        [$manager, $managerStore] = $this->makeStoreWithUser('Manager Store', StoreUserRole::Manager);
        Sanctum::actingAs($manager);
        $this->forStore($managerStore)->postJson('/api/v1/settings/tax-rates', $this->taxPayload('Manager Tax'))
            ->assertForbidden();

        [$admin, $adminStore] = $this->makeStoreWithUser('Admin Store', StoreUserRole::Admin);
        Sanctum::actingAs($admin);
        $this->forStore($adminStore)->getJson('/api/v1/settings')
            ->assertOk()->assertJsonPath('data.permissions.can_manage', true);
        $this->forStore($adminStore)->postJson('/api/v1/settings/shipping-methods', $this->shippingPayload('Admin Shipping'))
            ->assertCreated();
        $this->forStore($adminStore)->postJson('/api/v1/settings/tax-rates', $this->taxPayload('Admin Tax'))
            ->assertCreated();
    }

    public function test_api_created_fulfillment_configuration_is_consumed_by_checkout_engines(): void
    {
        [$user, $store] = $this->makeStoreWithUser('Checkout Compatible');
        $zone = $this->makeZone($store, 'Türkiye Checkout', 'TR');
        Sanctum::actingAs($user);

        $methodId = $this->forStore($store)->postJson('/api/v1/settings/shipping-methods', [
            ...$this->shippingPayload('API Kargo'),
            'price' => '35.00',
            'shipping_zone_id' => $zone->ulid,
        ])->assertCreated()->json('data.id');
        $taxId = $this->forStore($store)->postJson('/api/v1/settings/tax-rates', [
            ...$this->taxPayload('API KDV'),
            'rate' => '10.00',
            'is_inclusive' => false,
        ])->assertCreated()->json('data.id');

        app(CurrentStore::class)->set($store);
        $product = Product::query()->create([
            'title' => 'Checkout Product '.str()->random(6),
            'slug' => 'checkout-product-'.str()->lower(str()->random(10)),
            'status' => ProductStatus::Active,
            'is_taxable' => true,
        ]);
        $variant = $product->variants()->create([
            'title' => 'Default',
            'price' => '100.00',
            'status' => ProductStatus::Active,
            'is_taxable' => true,
        ]);
        $cartManager = app(CartManager::class);
        $cart = $cartManager->getOrCreate(currency: 'TRY');
        $cartManager->addItem($cart, $variant, 1);
        $cart = $cart->fresh();
        $customerManager = app(CustomerManager::class);
        $customer = $customerManager->findOrCreate(new UpsertCustomerData(email: 'fulfillment-checkout@example.test'));
        $address = $customerManager->createAddress($customer, new CustomerAddressData(
            type: CustomerAddressType::Shipping,
            firstName: 'Checkout',
            lastName: 'Test',
            countryCode: 'TR',
            addressLine1: 'Test Mahallesi 1',
            province: 'İstanbul',
            district: 'Kadıköy',
        ));

        $quotes = app(ShippingEngine::class)->quotes($cart, $address);
        $this->assertCount(1, $quotes);
        $this->assertSame($methodId, $quotes->first()->method->ulid);
        $this->assertSame('35.00', $quotes->first()->amount->toDecimal());

        $cart = app(TaxEngine::class)->apply($cart, $address);
        $this->assertSame('10.00', $cart->tax_total);
        $this->assertFalse($cart->tax_inclusive);
        $this->assertSame(
            TaxRate::query()->where('ulid', $taxId)->firstOrFail()->id,
            $cart->tax_rate_id,
        );
    }

    /** @return array{0: User, 1: Store} */
    private function makeStoreWithUser(string $name, StoreUserRole $role = StoreUserRole::Owner): array
    {
        $user = User::factory()->create();
        $merchant = Merchant::query()->create(['owner_user_id' => $user->id]);
        $store = $merchant->stores()->create([
            'name' => $name,
            'slug' => str($name)->slug().'-'.str()->lower(str()->random(6)),
            'country_code' => 'TR',
        ]);
        StoreUser::withoutGlobalScope(StoreScope::class)->create([
            'store_id' => $store->id,
            'user_id' => $user->id,
            'role' => $role,
            'status' => StoreUserStatus::Active,
            'joined_at' => now(),
        ]);

        return [$user, $store];
    }

    private function makeZone(Store $store, string $name, string $countryCode): ShippingZone
    {
        app(CurrentStore::class)->set($store);
        $zone = ShippingZone::query()->create([
            'name' => $name,
        ]);
        ShippingZoneRegion::query()->create([
            'shipping_zone_id' => $zone->id,
            'country_code' => $countryCode,
        ]);

        return $zone;
    }

    private function forStore(Store $store): static
    {
        return $this->withSession(['current_store_id' => $store->id]);
    }

    /** @return array<string, mixed> */
    private function shippingPayload(string $name): array
    {
        return [
            'name' => $name,
            'type' => 'flat_rate',
            'price' => '15.00',
            'estimated_days_min' => 2,
            'estimated_days_max' => 5,
            'status' => 'active',
        ];
    }

    /** @return array<string, mixed> */
    private function taxPayload(string $name): array
    {
        return [
            'name' => $name,
            'country_code' => 'TR',
            'rate' => '20.00',
            'is_inclusive' => true,
            'status' => 'active',
        ];
    }
}
