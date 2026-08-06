<?php

namespace Tests\Feature;

use App\Core\Tenancy\Scopes\StoreScope;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Enums\StoreUserRole;
use Modules\Store\Enums\StoreUserStatus;
use Modules\Store\Models\Store;
use Modules\Store\Models\StoreUser;
use Tests\TestCase;

class ActiveStoreContextTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Referer', 'https://app.rivaify.com');
    }

    public function test_me_selects_an_active_store_for_an_authenticated_merchant_session(): void
    {
        [$user, $store] = $this->makeStoreWithUser('Context Store');
        Sanctum::actingAs($user);

        $this->withSession([])
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('data.store.id', $store->ulid)
            ->assertSessionHas('current_store_id', $store->id);

        $this->withSession(['current_store_id' => $store->id])
            ->getJson('/api/v1/products')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function makeStoreWithUser(string $name): array
    {
        $user = User::factory()->create();
        $merchant = Merchant::create(['owner_user_id' => $user->id]);
        $store = $merchant->stores()->create([
            'name' => $name,
            'slug' => str($name)->slug().'-'.str()->random(8),
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