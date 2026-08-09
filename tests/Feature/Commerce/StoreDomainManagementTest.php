<?php

namespace Tests\Feature\Commerce;

use App\Core\Tenancy\CurrentStore;
use App\Core\Tenancy\Scopes\StoreScope;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Laravel\Sanctum\Sanctum;
use Modules\Commerce\Services\Store\StoreDomainVerifier;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Enums\StoreStatus;
use Modules\Store\Enums\StoreUserRole;
use Modules\Store\Enums\StoreUserStatus;
use Modules\Store\Models\Store;
use Modules\Store\Models\StoreDomain;
use Modules\Store\Models\StoreUser;
use Tests\TestCase;

class StoreDomainManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Referer', 'https://app.rivaify.com');
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_cname_or_txt_ownership_record_verifies_a_custom_domain(): void
    {
        [, $store] = $this->makeStoreWithOwner('DNS Store');
        app(CurrentStore::class)->set($store);
        $domain = StoreDomain::query()->create(['domain' => 'shop.example.com']);
        $verifier = new class([['target' => $store->slug.'.rivaify.com.']]) extends StoreDomainVerifier
        {
            public function __construct(private readonly array $cnameRecords) {}

            protected function records(string $hostname, int $type): array
            {
                return $type === DNS_CNAME ? $this->cnameRecords : [];
            }
        };

        $this->assertTrue($verifier->verify($domain, $store));
        $this->assertNotNull($domain->fresh()->verified_at);
    }

    public function test_only_a_verified_domain_can_be_made_primary(): void
    {
        [$user, $store] = $this->makeStoreWithOwner('Primary Store');
        app(CurrentStore::class)->set($store);
        $system = StoreDomain::query()->create([
            'domain' => $store->slug.'.rivaify.com',
            'is_primary' => true,
            'verified_at' => now(),
        ]);
        $custom = StoreDomain::query()->create(['domain' => 'primary.example.com']);
        Sanctum::actingAs($user);

        $this->withSession(['current_store_id' => $store->id])
            ->postJson('/api/v1/settings/domains/'.$custom->ulid.'/primary')
            ->assertUnprocessable();

        $custom->update(['verified_at' => now()]);
        $this->withSession(['current_store_id' => $store->id])
            ->postJson('/api/v1/settings/domains/'.$custom->ulid.'/primary')
            ->assertOk()
            ->assertJsonPath('data.is_primary', true);

        $this->assertFalse($system->fresh()->is_primary);
        $this->assertTrue($custom->fresh()->is_primary);
    }

    public function test_verified_custom_domain_serves_the_storefront_shell(): void
    {
        [, $store] = $this->makeStoreWithOwner('Custom Host Store');
        app(CurrentStore::class)->set($store);
        StoreDomain::query()->create([
            'domain' => 'storefront.example.com',
            'is_primary' => true,
            'verified_at' => now(),
        ]);
        app(CurrentStore::class)->clear();

        $this->get('http://storefront.example.com/products/demo')
            ->assertOk()
            ->assertSee('id="root"', false)
            ->assertSee($store->name.' · Rivaify');
    }

    /** @return array{0: User, 1: Store} */
    private function makeStoreWithOwner(string $name): array
    {
        $user = User::factory()->create();
        $merchant = Merchant::query()->create(['owner_user_id' => $user->id]);
        $store = $merchant->stores()->create([
            'name' => $name,
            'slug' => str($name)->slug().'-'.str()->lower(str()->random(6)),
            'status' => StoreStatus::Active,
        ]);
        StoreUser::withoutGlobalScope(StoreScope::class)->create([
            'store_id' => $store->id,
            'user_id' => $user->id,
            'role' => StoreUserRole::Owner,
            'status' => StoreUserStatus::Active,
            'joined_at' => now(),
        ]);

        return [$user, $store];
    }
}
