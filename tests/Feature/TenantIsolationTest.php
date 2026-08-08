<?php

namespace Tests\Feature;

use App\Core\Tenancy\CurrentStore;
use App\Core\Tenancy\Exceptions\StoreContextMissingException;
use App\Core\Tenancy\Scopes\StoreScope;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Enums\StoreUserRole;
use Modules\Store\Enums\StoreUserStatus;
use Modules\Store\Models\Store;
use Modules\Store\Models\StoreDomain;
use Modules\Store\Models\StoreUser;
use Tests\TestCase;

/**
 * The single most important behavior in this codebase (brief §7): a
 * store-scoped query must be structurally incapable of leaking another
 * tenant's rows, and must fail loudly rather than silently when no tenant
 * context is bound.
 */
class TenantIsolationTest extends TestCase
{
    use DatabaseTransactions;

    private function makeStoreWithUser(string $storeName): array
    {
        $user = User::factory()->create();
        $merchant = Merchant::create(['owner_user_id' => $user->id]);
        $store = $merchant->stores()->create(['name' => $storeName, 'slug' => str($storeName)->slug()]);
        StoreUser::withoutGlobalScope(StoreScope::class)->create([
            'store_id' => $store->id,
            'user_id' => $user->id,
            'role' => StoreUserRole::Owner,
            'status' => StoreUserStatus::Active,
            'joined_at' => now(),
        ]);

        return [$user, $store];
    }

    public function test_scoped_query_without_current_store_throws(): void
    {
        $this->expectException(StoreContextMissingException::class);

        StoreUser::query()->get();
    }

    public function test_scoped_query_only_returns_the_bound_stores_rows(): void
    {
        [, $storeA] = $this->makeStoreWithUser('Store A');
        [, $storeB] = $this->makeStoreWithUser('Store B');

        app(CurrentStore::class)->set($storeA);
        $rowsForA = StoreUser::query()->get();

        $this->assertCount(1, $rowsForA);
        $this->assertTrue($rowsForA->every(fn (StoreUser $su) => $su->store_id === $storeA->id));
        $this->assertFalse($rowsForA->contains('store_id', $storeB->id));
    }

    public function test_switching_current_store_changes_the_visible_rows(): void
    {
        [, $storeA] = $this->makeStoreWithUser('Store A');
        [, $storeB] = $this->makeStoreWithUser('Store B');

        $currentStore = app(CurrentStore::class);

        $currentStore->set($storeA);
        $this->assertSame($storeA->id, StoreUser::query()->sole()->store_id);

        $currentStore->set($storeB);
        $this->assertSame($storeB->id, StoreUser::query()->sole()->store_id);
    }

    public function test_creating_a_scoped_model_auto_fills_store_id_from_current_store(): void
    {
        [$user, $store] = $this->makeStoreWithUser('Auto Fill Store');
        app(CurrentStore::class)->set($store);

        $domain = StoreDomain::create(['domain' => 'auto-fill.example.test']);

        $this->assertSame($store->id, $domain->store_id);
    }

    public function test_admin_can_deliberately_bypass_the_scope_for_cross_tenant_access(): void
    {
        [, $storeA] = $this->makeStoreWithUser('Store A');
        [, $storeB] = $this->makeStoreWithUser('Store B');

        $all = StoreUser::withoutGlobalScope(StoreScope::class)
            ->whereIn('store_id', [$storeA->id, $storeB->id])
            ->get();

        $this->assertCount(2, $all);
        $this->assertTrue($all->contains('store_id', $storeA->id));
        $this->assertTrue($all->contains('store_id', $storeB->id));
    }
}
