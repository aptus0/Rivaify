<?php

namespace Tests\Feature\Commerce;

use App\Core\Tenancy\CurrentStore;
use App\Core\Tenancy\Scopes\StoreScope;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Modules\Commerce\Enums\Catalog\BrandStatus;
use Modules\Commerce\Enums\Catalog\CategoryStatus;
use Modules\Commerce\Models\Catalog\Brand;
use Modules\Commerce\Models\Catalog\Category;
use Modules\Commerce\Models\Inventory\InventoryLocation;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Enums\StoreUserRole;
use Modules\Store\Enums\StoreUserStatus;
use Modules\Store\Models\Store;
use Modules\Store\Models\StoreUser;
use Tests\TestCase;

class AdminProductApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Referer', 'https://app.rivaify.com');
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_owner_can_create_search_and_update_product_variants_with_location_inventory(): void
    {
        [$owner, $store] = $this->makeStoreWithUser('Product API Store', StoreUserRole::Owner);
        app(CurrentStore::class)->set($store);
        $category = Category::query()->create([
            'name' => 'Ayakkabı',
            'slug' => 'ayakkabi-'.str()->random(6),
            'status' => CategoryStatus::Active,
        ]);
        $brand = Brand::query()->create([
            'name' => 'Nike',
            'slug' => 'nike-'.str()->random(6),
            'status' => BrandStatus::Active,
        ]);
        $storeLocation = InventoryLocation::query()->create([
            'name' => 'Demo Mağaza',
            'code' => 'DEMO-'.str()->random(4),
        ]);
        $warehouse = InventoryLocation::query()->create([
            'name' => 'Bursa Depo',
            'code' => 'BURSA-'.str()->random(4),
        ]);
        app(CurrentStore::class)->clear();
        Sanctum::actingAs($owner);

        $created = $this->withSession(['current_store_id' => $store->id])
            ->postJson('/api/v1/products', $this->productPayload(
                $category->ulid,
                $brand->ulid,
                $storeLocation->ulid,
                $warehouse->ulid,
            ))
            ->assertCreated()
            ->assertJsonPath('data.title', 'Nike Air Max')
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.variant_count', 6)
            ->assertJsonPath('data.inventory.sellable', 42)
            ->assertJsonPath('data.category.name', 'Ayakkabı')
            ->assertJsonPath('data.brand.name', 'Nike');
        $productId = $created->json('data.id');

        $this->withSession(['current_store_id' => $store->id])
            ->getJson('/api/v1/products?q=NK-AM-BLK-42&status=active')
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $productId)
            ->assertJsonPath('data.0.inventory.sellable', 42)
            ->assertJsonPath('summary.active', 1);

        $updated = $this->withSession(['current_store_id' => $store->id])
            ->patchJson("/api/v1/products/{$productId}", $this->productPayload(
                $category->ulid,
                $brand->ulid,
                $storeLocation->ulid,
                $warehouse->ulid,
                '<p>Güvenli metin</p><script>alert(1)</script>',
            ))
            ->assertOk()
            ->assertJsonPath('data.id', $productId);

        $this->assertStringNotContainsString('<script>', $updated->json('data.description'));
        $this->assertStringContainsString('Güvenli metin', $updated->json('data.description'));
    }

    public function test_staff_member_cannot_mutate_products(): void
    {
        [$owner, $store] = $this->makeStoreWithUser('Product Permission Store', StoreUserRole::Owner);
        $staff = User::factory()->create();
        StoreUser::withoutGlobalScope(StoreScope::class)->create([
            'store_id' => $store->id,
            'user_id' => $staff->id,
            'role' => StoreUserRole::Staff,
            'status' => StoreUserStatus::Active,
            'joined_at' => now(),
        ]);
        Sanctum::actingAs($staff);

        $this->withSession(['current_store_id' => $store->id])
            ->postJson('/api/v1/products', [
                'title' => 'Denied Product',
                'product_type' => 'physical',
                'status' => 'draft',
                'is_taxable' => true,
                'requires_shipping' => true,
            ])
            ->assertForbidden();

        $this->assertNotNull($owner);
    }

    /**
     * @return array<string, mixed>
     */
    private function productPayload(string $categoryId, string $brandId, string $storeLocationId, string $warehouseId, ?string $description = null): array
    {
        $variants = [];
        foreach (['Black', 'White'] as $color) {
            foreach (['41', '42', '43'] as $size) {
                $isTarget = $color === 'Black' && $size === '42';
                $variants[] = [
                    'title' => "{$color} / {$size}",
                    'price' => '4499.00',
                    'cost_price' => '2700.00',
                    'sku' => $isTarget ? 'NK-AM-BLK-42' : "NK-AM-{$color}-{$size}",
                    'barcode' => $isTarget ? '8691234567890' : null,
                    'weight' => '1.200',
                    'weight_unit' => 'kg',
                    'requires_shipping' => true,
                    'is_taxable' => true,
                    'status' => 'active',
                    'track_inventory' => true,
                    'inventory' => $isTarget ? [
                        ['location_id' => $storeLocationId, 'available_quantity' => 12],
                        ['location_id' => $warehouseId, 'available_quantity' => 30],
                    ] : [],
                ];
            }
        }

        return [
            'title' => 'Nike Air Max',
            'description' => $description ?? '<p>Nike Air Max erkek spor ayakkabı</p>',
            'slug' => 'nike-air-max',
            'category_id' => $categoryId,
            'brand_id' => $brandId,
            'product_type' => 'physical',
            'status' => 'active',
            'vendor' => 'Nike',
            'is_taxable' => true,
            'requires_shipping' => true,
            'meta_title' => 'Nike Air Max Erkek Ayakkabı | Demo Mağaza',
            'meta_description' => 'Nike Air Max erkek spor ayakkabı.',
            'package' => ['width' => '30.00', 'height' => '12.00', 'length' => '40.00', 'dimension_unit' => 'cm'],
            'tags' => ['Spor', 'Erkek'],
            'options' => [
                ['name' => 'Renk', 'values' => ['Black', 'White']],
                ['name' => 'Beden', 'values' => ['41', '42', '43']],
            ],
            'variants' => $variants,
        ];
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