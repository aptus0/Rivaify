<?php

namespace Tests\Feature\Commerce;

use App\Core\Tenancy\Scopes\StoreScope;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Enums\StoreStatus;
use Modules\Store\Enums\StoreUserRole;
use Modules\Store\Enums\StoreUserStatus;
use Modules\Store\Models\Store;
use Modules\Store\Models\StoreDomain;
use Modules\Store\Models\StoreUser;
use Tests\TestCase;

class AdminDiscountSettingsApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Referer', 'https://app.rivaify.com');
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_discount_management_supports_filtering_detail_validation_and_delete(): void
    {
        [$user, $store] = $this->makeStoreWithUser('Discount Suite');
        Sanctum::actingAs($user);

        $created = $this->withSession(['current_store_id' => $store->id])
            ->postJson('/api/v1/discounts', [
                'name' => 'Yaz Fırsatı',
                'code' => 'yaz20',
                'type' => 'percentage',
                'value' => '20',
                'status' => 'active',
                'usage_limit' => 100,
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'YAZ20');
        $discountId = $created->json('data.id');

        $this->withSession(['current_store_id' => $store->id])
            ->postJson('/api/v1/discounts', [
                'name' => 'Eski Kampanya',
                'type' => 'fixed_amount',
                'value' => '50',
                'status' => 'inactive',
            ])
            ->assertCreated();

        $this->withSession(['current_store_id' => $store->id])
            ->getJson('/api/v1/discounts?q=yaz&status=active')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $discountId)
            ->assertJsonPath('currency', 'TRY')
            ->assertJsonPath('summary.all', 2)
            ->assertJsonPath('summary.active', 1)
            ->assertJsonPath('summary.inactive', 1);

        $this->withSession(['current_store_id' => $store->id])
            ->getJson("/api/v1/discounts/{$discountId}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Yaz Fırsatı');

        $this->withSession(['current_store_id' => $store->id])
            ->postJson('/api/v1/discounts', [
                'name' => 'Geçersiz Yüzde',
                'type' => 'percentage',
                'value' => '101',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('value');

        $this->withSession(['current_store_id' => $store->id])
            ->patchJson("/api/v1/discounts/{$discountId}", ['value' => '101'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('value');

        $this->withSession(['current_store_id' => $store->id])
            ->patchJson("/api/v1/discounts/{$discountId}", ['type' => 'free_shipping'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('value');

        $this->withSession(['current_store_id' => $store->id])
            ->deleteJson("/api/v1/discounts/{$discountId}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->withSession(['current_store_id' => $store->id])
            ->getJson("/api/v1/discounts/{$discountId}")
            ->assertNotFound();
    }

    public function test_settings_returns_readiness_without_leaking_paytr_credentials(): void
    {
        [$user, $store] = $this->makeStoreWithUser('Secure Settings', StoreStatus::Active);
        $this->createSystemDomain($store);
        Sanctum::actingAs($user);

        config()->set('commerce.payments.default', 'paytr');
        config()->set('commerce.payments.paytr.merchant_id', 'merchant-id-visible-nowhere');
        config()->set('commerce.payments.paytr.merchant_key', 'merchant-key-super-secret');
        config()->set('commerce.payments.paytr.merchant_salt', 'merchant-salt-super-secret');
        config()->set('commerce.payments.paytr.test_mode', true);

        $response = $this->withSession(['current_store_id' => $store->id])
            ->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.store.name', 'Secure Settings')
            ->assertJsonPath('data.domains.0.verified', true)
            ->assertJsonPath('data.payments.default_provider', 'paytr')
            ->assertJsonPath('data.payments.paytr.configured', true)
            ->assertJsonPath('data.payments.paytr.enabled', true)
            ->assertJsonPath('data.payments.paytr.test_mode', true);

        $json = $response->getContent();
        $this->assertStringNotContainsString('merchant-id-visible-nowhere', $json);
        $this->assertStringNotContainsString('merchant-key-super-secret', $json);
        $this->assertStringNotContainsString('merchant-salt-super-secret', $json);

        $this->withSession(['current_store_id' => $store->id])
            ->getJson('/api/v1/integrations')
            ->assertOk()
            ->assertJsonPath('data.channels.0.id', 'online_store')
            ->assertJsonPath('data.channels.0.status', 'active')
            ->assertJsonPath('data.apps.0.id', 'paytr')
            ->assertJsonPath('data.apps.0.status', 'test_mode');
    }

    public function test_store_profile_and_pending_custom_domains_can_be_managed_safely(): void
    {
        [$user, $store] = $this->makeStoreWithUser('Profile Store');
        $primary = $this->createSystemDomain($store);
        Sanctum::actingAs($user);

        $this->withSession(['current_store_id' => $store->id])
            ->patchJson('/api/v1/settings/store', [
                'name' => 'Yeni Mağaza Adı',
                'default_currency' => 'EUR',
                'default_locale' => 'en',
                'timezone' => 'Europe/Berlin',
                'country_code' => 'de',
            ])
            ->assertOk()
            ->assertJsonPath('data.store.name', 'Yeni Mağaza Adı')
            ->assertJsonPath('data.store.default_currency', 'EUR')
            ->assertJsonPath('data.store.country_code', 'DE');

        $custom = $this->withSession(['current_store_id' => $store->id])
            ->postJson('/api/v1/settings/domains', ['domain' => ' Shop.Example.COM. '])
            ->assertCreated()
            ->assertJsonPath('data.domain', 'shop.example.com')
            ->assertJsonPath('data.verified', false)
            ->assertJsonPath('data.is_primary', false);
        $customId = $custom->json('data.id');

        $this->assertDatabaseHas('store_domains', [
            'store_id' => $store->id,
            'domain' => 'shop.example.com',
            'verified_at' => null,
        ]);

        $this->withSession(['current_store_id' => $store->id])
            ->deleteJson("/api/v1/settings/domains/{$primary->ulid}")
            ->assertUnprocessable()
            ->assertJsonPath('message', 'primary_domain_cannot_be_deleted');

        $this->withSession(['current_store_id' => $store->id])
            ->deleteJson("/api/v1/settings/domains/{$customId}")
            ->assertOk()
            ->assertJsonPath('data.deleted', true);
    }

    public function test_domain_and_discount_routes_preserve_store_isolation(): void
    {
        [$userA, $storeA] = $this->makeStoreWithUser('Isolation A');
        [$userB, $storeB] = $this->makeStoreWithUser('Isolation B');
        $foreignDomain = StoreDomain::withoutGlobalScope(StoreScope::class)->create([
            'store_id' => $storeB->id,
            'domain' => 'foreign.example.com',
            'is_primary' => false,
        ]);

        Sanctum::actingAs($userB);
        $foreignDiscount = $this->withSession(['current_store_id' => $storeB->id])
            ->postJson('/api/v1/discounts', [
                'name' => 'Foreign Discount',
                'type' => 'fixed_amount',
                'value' => '10',
            ])
            ->assertCreated()
            ->json('data.id');

        Sanctum::actingAs($userA);
        $this->withSession(['current_store_id' => $storeA->id])
            ->deleteJson("/api/v1/settings/domains/{$foreignDomain->ulid}")
            ->assertNotFound();
        $this->withSession(['current_store_id' => $storeA->id])
            ->getJson("/api/v1/discounts/{$foreignDiscount}")
            ->assertNotFound();
    }

    public function test_settings_and_discount_mutations_require_the_expected_store_roles(): void
    {
        [$staff, $staffStore] = $this->makeStoreWithUser('Staff Permissions', StoreStatus::Draft, StoreUserRole::Staff);
        Sanctum::actingAs($staff);

        $this->withSession(['current_store_id' => $staffStore->id])
            ->getJson('/api/v1/settings')
            ->assertOk();
        $this->withSession(['current_store_id' => $staffStore->id])
            ->patchJson('/api/v1/settings/store', ['name' => 'Yetkisiz Değişiklik'])
            ->assertForbidden();
        $this->withSession(['current_store_id' => $staffStore->id])
            ->getJson('/api/v1/discounts')
            ->assertOk();
        $this->withSession(['current_store_id' => $staffStore->id])
            ->postJson('/api/v1/discounts', ['name' => 'Yetkisiz', 'type' => 'fixed_amount', 'value' => '10'])
            ->assertForbidden();

        [$manager, $managerStore] = $this->makeStoreWithUser('Manager Permissions', StoreStatus::Draft, StoreUserRole::Manager);
        Sanctum::actingAs($manager);
        $this->withSession(['current_store_id' => $managerStore->id])
            ->postJson('/api/v1/discounts', ['name' => 'Yetkili', 'type' => 'fixed_amount', 'value' => '10'])
            ->assertCreated();
        $this->withSession(['current_store_id' => $managerStore->id])
            ->patchJson('/api/v1/settings/store', ['name' => 'Yine Yetkisiz'])
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function makeStoreWithUser(
        string $name,
        StoreStatus $status = StoreStatus::Draft,
        StoreUserRole $role = StoreUserRole::Owner,
    ): array {
        $user = User::factory()->create();
        $merchant = Merchant::query()->create(['owner_user_id' => $user->id]);
        $store = $merchant->stores()->create([
            'name' => $name,
            'slug' => str($name)->slug().'-'.str()->lower(str()->random(6)),
            'status' => $status,
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

    private function createSystemDomain(Store $store): StoreDomain
    {
        return StoreDomain::withoutGlobalScope(StoreScope::class)->create([
            'store_id' => $store->id,
            'domain' => "{$store->slug}.rivaify.com",
            'is_primary' => true,
            'verified_at' => now(),
        ]);
    }
}
