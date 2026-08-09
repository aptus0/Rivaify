<?php

namespace Tests\Feature\Commerce;

use App\Core\Tenancy\CurrentStore;
use App\Core\Tenancy\Scopes\StoreScope;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Modules\Commerce\Enums\Catalog\ProductStatus;
use Modules\Commerce\Enums\Catalog\ProductType;
use Modules\Commerce\Models\Catalog\Category;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Models\Catalog\ProductCollection;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Enums\StoreUserRole;
use Modules\Store\Enums\StoreUserStatus;
use Modules\Store\Models\Store;
use Modules\Store\Models\StoreUser;
use Tests\TestCase;

class AdminCatalogManagementApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withHeader('Referer', 'https://app.rivaify.com');
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_owner_can_create_update_list_and_delete_hierarchical_categories(): void
    {
        [$owner, $store] = $this->makeStoreWithOwner('Category Management Store');
        Sanctum::actingAs($owner);

        $root = $this->withSession(['current_store_id' => $store->id])
            ->postJson('/api/v1/categories', [
                'name' => 'Ayakkabı',
                'description' => 'Tüm ayakkabılar',
                'status' => 'active',
            ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Ayakkabı')
            ->assertJsonPath('data.slug', 'ayakkabi')
            ->assertJsonPath('data.product_count', 0);
        $rootId = $root->json('data.id');

        $child = $this->withSession(['current_store_id' => $store->id])
            ->postJson('/api/v1/categories', [
                'name' => 'Spor Ayakkabı',
                'parent_id' => $rootId,
                'status' => 'draft',
                'position' => 3,
            ])
            ->assertCreated()
            ->assertJsonPath('data.parent.id', $rootId)
            ->assertJsonPath('data.position', 3);
        $childId = $child->json('data.id');

        app(CurrentStore::class)->set($store);
        $category = Category::query()->where('ulid', $childId)->firstOrFail();
        Product::query()->create([
            'title' => 'Koşu Ayakkabısı',
            'slug' => 'kosu-ayakkabisi',
            'category_id' => $category->id,
            'product_type' => ProductType::Physical,
            'status' => ProductStatus::Active,
        ]);
        app(CurrentStore::class)->clear();

        $this->withSession(['current_store_id' => $store->id])
            ->getJson('/api/v1/categories?q=spor')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $childId)
            ->assertJsonPath('data.0.product_count', 1);

        $this->withSession(['current_store_id' => $store->id])
            ->patchJson("/api/v1/categories/{$childId}", [
                'name' => 'Koşu Ayakkabıları',
                'slug' => 'kosu',
                'status' => 'active',
                'parent_id' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.slug', 'kosu')
            ->assertJsonPath('data.parent', null);

        $this->withSession(['current_store_id' => $store->id])
            ->patchJson("/api/v1/categories/{$rootId}", ['parent_id' => $rootId])
            ->assertUnprocessable();

        $this->withSession(['current_store_id' => $store->id])
            ->deleteJson("/api/v1/categories/{$childId}")
            ->assertNoContent();

        app(CurrentStore::class)->set($store);
        $this->assertNull(Product::query()->where('slug', 'kosu-ayakkabisi')->firstOrFail()->category_id);
        app(CurrentStore::class)->clear();
    }

    public function test_owner_can_manage_ordered_collection_membership_with_tenant_isolation(): void
    {
        [$ownerA, $storeA] = $this->makeStoreWithOwner('Collection Store A');
        [, $storeB] = $this->makeStoreWithOwner('Collection Store B');
        app(CurrentStore::class)->set($storeA);
        $first = $this->product('Birinci Ürün', 'birinci-urun');
        $second = $this->product('İkinci Ürün', 'ikinci-urun');
        app(CurrentStore::class)->set($storeB);
        $foreign = $this->product('Yabancı Ürün', 'yabanci-urun');
        $foreignCollection = ProductCollection::query()->create([
            'name' => 'Yabancı Koleksiyon',
            'slug' => 'yabanci-koleksiyon',
        ]);
        app(CurrentStore::class)->clear();
        Sanctum::actingAs($ownerA);

        $created = $this->withSession(['current_store_id' => $storeA->id])
            ->postJson('/api/v1/collections', [
                'name' => 'Yaz Seçkisi',
                'status' => 'active',
                'product_ids' => [$second->ulid, $first->ulid],
            ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'yaz-seckisi')
            ->assertJsonPath('data.product_count', 2)
            ->assertJsonPath('data.products.0.id', $second->ulid)
            ->assertJsonPath('data.products.1.id', $first->ulid);
        $collectionId = $created->json('data.id');

        $this->withSession(['current_store_id' => $storeA->id])
            ->putJson("/api/v1/collections/{$collectionId}/products", [
                'product_ids' => [$first->ulid],
            ])
            ->assertOk()
            ->assertJsonPath('data.product_count', 1)
            ->assertJsonPath('data.products.0.id', $first->ulid);

        $this->withSession(['current_store_id' => $storeA->id])
            ->patchJson("/api/v1/collections/{$collectionId}", [
                'name' => 'Yeni Yaz Seçkisi',
                'slug' => 'yeni-yaz',
                'product_ids' => [$foreign->ulid],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_ids');

        $this->withSession(['current_store_id' => $storeA->id])
            ->getJson("/api/v1/collections/{$foreignCollection->ulid}")
            ->assertNotFound();

        $this->withSession(['current_store_id' => $storeA->id])
            ->deleteJson("/api/v1/collections/{$collectionId}")
            ->assertNoContent();
    }

    private function product(string $title, string $slug): Product
    {
        return Product::query()->create([
            'title' => $title,
            'slug' => $slug,
            'product_type' => ProductType::Physical,
            'status' => ProductStatus::Active,
        ]);
    }

    /** @return array{0: User, 1: Store} */
    private function makeStoreWithOwner(string $name): array
    {
        $user = User::factory()->create();
        $merchant = Merchant::create(['owner_user_id' => $user->id]);
        $store = $merchant->stores()->create(['name' => $name, 'slug' => str($name)->slug().'-'.str()->random(8)]);
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

