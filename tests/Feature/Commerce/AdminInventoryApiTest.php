<?php

namespace Tests\Feature\Commerce;

use App\Core\Tenancy\CurrentStore;
use App\Core\Tenancy\Scopes\StoreScope;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Modules\Commerce\Enums\Catalog\ProductStatus;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Models\Inventory\InventoryMovement;
use Modules\Commerce\Services\Inventory\InventoryManager;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Enums\StoreUserRole;
use Modules\Store\Enums\StoreUserStatus;
use Modules\Store\Models\Store;
use Modules\Store\Models\StoreUser;
use Tests\TestCase;

class AdminInventoryApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Referer', 'https://app.rivaify.com');
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_owner_can_list_filter_and_adjust_store_scoped_inventory(): void
    {
        [$owner, $store] = $this->makeStoreWithUser('Inventory API Store', StoreUserRole::Owner);
        app(CurrentStore::class)->set($store);
        $location = app(InventoryManager::class)->createLocation('Ana Depo', 'MAIN'.str()->random(3));
        $product = Product::query()->create([
            'title' => 'Keten Gömlek',
            'slug' => 'keten-gomlek-'.str()->random(8),
            'status' => ProductStatus::Active,
        ]);
        $variant = $product->variants()->create([
            'title' => 'Mavi / M',
            'sku' => 'KG-MAVI-M',
            'price' => '749.00',
            'status' => ProductStatus::Active,
        ]);
        $inventory = app(InventoryManager::class);
        $item = $inventory->setTracking($variant, true, true);
        $level = $inventory->setAvailable($variant, $location, 8, 'initial_count');
        $level->update(['reserved_quantity' => 2, 'incoming_quantity' => 4]);
        app(CurrentStore::class)->clear();
        Sanctum::actingAs($owner);

        $this->withSession(['current_store_id' => $store->id])
            ->getJson('/api/v1/inventory?q=KG-MAVI-M&status=in_stock')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $item->ulid)
            ->assertJsonPath('data.0.product.title', 'Keten Gömlek')
            ->assertJsonPath('data.0.quantities.available', 8)
            ->assertJsonPath('data.0.quantities.reserved', 2)
            ->assertJsonPath('data.0.quantities.sellable', 6)
            ->assertJsonPath('data.0.quantities.incoming', 4)
            ->assertJsonPath('data.0.status', 'in_stock')
            ->assertJsonPath('summary.sellable', 6)
            ->assertJsonPath('summary.incoming', 4)
            ->assertJsonPath('locations.0.id', $location->ulid);

        $this->withSession(['current_store_id' => $store->id])
            ->patchJson("/api/v1/inventory/{$item->ulid}/locations/{$location->ulid}", [
                'available_quantity' => 5,
                'reason' => 'Haftalık sayım',
            ])
            ->assertOk()
            ->assertJsonPath('data.allow_oversell', true)
            ->assertJsonPath('data.quantities.available', 5)
            ->assertJsonPath('data.quantities.reserved', 2)
            ->assertJsonPath('data.quantities.sellable', 3)
            ->assertJsonPath('data.status', 'low_stock');

        app(CurrentStore::class)->set($store);
        $this->assertTrue($item->fresh()->allow_oversell);
        $this->assertDatabaseHas('inventory_movements', [
            'store_id' => $store->id,
            'inventory_item_id' => $item->id,
            'inventory_location_id' => $location->id,
            'quantity_delta' => -3,
            'reason' => 'Haftalık sayım',
            'created_by' => $owner->id,
        ]);
        $this->assertSame(2, InventoryMovement::query()->count());
        app(CurrentStore::class)->clear();

        $this->withSession(['current_store_id' => $store->id])
            ->patchJson("/api/v1/inventory/{$item->ulid}/locations/{$location->ulid}", [
                'available_quantity' => 1,
            ])
            ->assertUnprocessable();
    }

    public function test_inventory_adjustment_is_permission_and_tenant_isolated(): void
    {
        [$owner, $store] = $this->makeStoreWithUser('Inventory Owner Store', StoreUserRole::Owner);
        [$otherOwner, $otherStore] = $this->makeStoreWithUser('Other Inventory Store', StoreUserRole::Owner);
        app(CurrentStore::class)->set($otherStore);
        $otherLocation = app(InventoryManager::class)->createLocation('Other Depot');
        $otherProduct = Product::query()->create([
            'title' => 'Other Product',
            'slug' => 'other-product-'.str()->random(8),
        ]);
        $otherVariant = $otherProduct->variants()->create(['title' => 'Default', 'price' => '10.00']);
        $otherItem = app(InventoryManager::class)->setTracking($otherVariant, true);
        app(InventoryManager::class)->setAvailable($otherVariant, $otherLocation, 10);
        app(CurrentStore::class)->clear();

        Sanctum::actingAs($owner);
        $this->withSession(['current_store_id' => $store->id])
            ->patchJson("/api/v1/inventory/{$otherItem->ulid}/locations/{$otherLocation->ulid}", ['available_quantity' => 2])
            ->assertNotFound();

        $staff = User::factory()->create();
        StoreUser::withoutGlobalScope(StoreScope::class)->create([
            'store_id' => $otherStore->id,
            'user_id' => $staff->id,
            'role' => StoreUserRole::Staff,
            'status' => StoreUserStatus::Active,
            'joined_at' => now(),
        ]);
        Sanctum::actingAs($staff);
        $this->withSession(['current_store_id' => $otherStore->id])
            ->getJson('/api/v1/inventory')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
        $this->withSession(['current_store_id' => $otherStore->id])
            ->patchJson("/api/v1/inventory/{$otherItem->ulid}/locations/{$otherLocation->ulid}", ['available_quantity' => 2])
            ->assertForbidden();

        $this->assertNotNull($otherOwner);
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function makeStoreWithUser(string $name, StoreUserRole $role): array
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
            'role' => $role,
            'status' => StoreUserStatus::Active,
            'joined_at' => now(),
        ]);

        return [$user, $store];
    }
}
