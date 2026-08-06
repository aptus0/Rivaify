<?php

namespace Tests\Feature\Commerce;

use App\Core\Tenancy\CurrentStore;
use App\Core\Tenancy\Scopes\StoreScope;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Commerce\DTOs\Catalog\ProductEditorData;
use Modules\Commerce\DTOs\Catalog\ProductOptionInputData;
use Modules\Commerce\DTOs\Catalog\ProductVariantEditorData;
use Modules\Commerce\Enums\Catalog\BrandStatus;
use Modules\Commerce\Enums\Catalog\CategoryStatus;
use Modules\Commerce\Enums\Catalog\ProductStatus;
use Modules\Commerce\Enums\Catalog\ProductType;
use Modules\Commerce\Models\Catalog\Brand;
use Modules\Commerce\Models\Catalog\Category;
use Modules\Commerce\Models\Inventory\InventoryLocation;
use Modules\Commerce\Services\Catalog\ProductEditor;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Enums\StoreUserRole;
use Modules\Store\Enums\StoreUserStatus;
use Modules\Store\Models\Store;
use Modules\Store\Models\StoreUser;
use Tests\TestCase;

class ProductEditorTest extends TestCase
{
    use DatabaseTransactions;

    public function test_merchant_can_create_product_variants_and_location_inventory(): void
    {
        $store = $this->makeStore('Demo Mağaza');
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

        $product = app(ProductEditor::class)->create(new ProductEditorData(
            title: 'Nike Air Max',
            description: '<p>Nike Air Max erkek spor ayakkabı</p>',
            slug: 'nike-air-max',
            categoryId: $category->id,
            brandId: $brand->id,
            productType: ProductType::Physical,
            status: ProductStatus::Active,
            vendor: 'Nike',
            isTaxable: true,
            requiresShipping: true,
            metaTitle: 'Nike Air Max Erkek Ayakkabı | Demo Mağaza',
            metaDescription: 'Nike Air Max erkek spor ayakkabı.',
            packageWidth: '30.00',
            packageHeight: '12.00',
            packageLength: '40.00',
            tags: ['Spor', 'Erkek', 'Spor'],
            options: [
                new ProductOptionInputData('Renk', ['Black', 'White']),
                new ProductOptionInputData('Beden', ['41', '42', '43']),
            ],
            variants: $this->variantInputs($storeLocation->id, $warehouse->id),
        ));

        $product->load(['category', 'brand', 'tags', 'variants.inventoryItem.levels']);
        $black42 = $product->variants->firstWhere('title', 'Black / 42');

        $this->assertSame(ProductStatus::Active, $product->status);
        $this->assertSame('Ayakkabı', $product->category->name);
        $this->assertSame('Nike', $product->brand->name);
        $this->assertSame(['Erkek', 'Spor'], $product->tags->pluck('name')->all());
        $this->assertCount(6, $product->variants);
        $this->assertSame('NK-AM-BLK-42', $black42->sku);
        $this->assertSame('8691234567890', $black42->barcode);
        $this->assertSame('4499.00', $black42->price);
        $this->assertSame(42, $black42->inventoryItem->levels->sum('available_quantity'));
        $this->assertSame(42, $black42->inventoryItem->levels->sum(fn ($level): int => $level->sellableQuantity()));
    }

    /**
     * @return ProductVariantEditorData[]
     */
    private function variantInputs(int $storeLocationId, int $warehouseId): array
    {
        $inputs = [];
        foreach (['Black', 'White'] as $color) {
            foreach (['41', '42', '43'] as $size) {
                $isTarget = $color === 'Black' && $size === '42';
                $inputs[] = new ProductVariantEditorData(
                    title: "{$color} / {$size}",
                    price: '4499.00',
                    costPrice: '2700.00',
                    sku: $isTarget ? 'NK-AM-BLK-42' : "NK-AM-{$color}-{$size}",
                    barcode: $isTarget ? '8691234567890' : null,
                    weight: '1.200',
                    status: ProductStatus::Active,
                    inventoryByLocationId: $isTarget ? [
                        $storeLocationId => 12,
                        $warehouseId => 30,
                    ] : [],
                );
            }
        }

        return $inputs;
    }

    private function makeStore(string $name): Store
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

        return $store;
    }
}