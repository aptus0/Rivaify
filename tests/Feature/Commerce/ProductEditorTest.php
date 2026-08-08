<?php

namespace Tests\Feature\Commerce;

use App\Core\Tenancy\CurrentStore;
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
use Modules\Commerce\Services\Catalog\ProductEditor;
use Modules\Commerce\Services\Inventory\InventoryManager;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Models\Store;
use Tests\TestCase;

class ProductEditorTest extends TestCase
{
    use DatabaseTransactions;

    public function test_merchant_can_create_a_product_with_variant_matrix_and_location_inventory(): void
    {
        $store = $this->makeStore('Demo Mağaza');
        app(CurrentStore::class)->set($store);
        $category = Category::query()->create([
            'name' => 'Ayakkabı',
            'slug' => 'ayakkabi',
            'status' => CategoryStatus::Active,
        ]);
        $brand = Brand::query()->create([
            'name' => 'Nike',
            'slug' => 'nike',
            'status' => BrandStatus::Active,
        ]);
        $inventory = app(InventoryManager::class);
        $storeLocation = $inventory->createLocation('Demo Mağaza', 'DEMO');
        $warehouse = $inventory->createLocation('Bursa Depo', 'BURSA');

        $product = app(ProductEditor::class)->create(new ProductEditorData(
            title: 'Nike Air Max',
            description: 'Nike Air Max erkek spor ayakkabı',
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
                new ProductOptionInputData('Color', ['Black', 'White']),
                new ProductOptionInputData('Size', ['41', '42', '43']),
            ],
            variants: [
                new ProductVariantEditorData(
                    title: 'Black / 42',
                    price: '4499.00',
                    costPrice: '2700.00',
                    sku: 'NK-AM-BLK-42',
                    barcode: '8691234567890',
                    weight: '1.200',
                    status: ProductStatus::Active,
                    inventoryByLocationId: [
                        $storeLocation->id => 12,
                        $warehouse->id => 30,
                    ],
                ),
            ],
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
        $this->assertSame(42, $black42->inventoryItem->levels->sum(
            fn ($level): int => $level->available_quantity - $level->reserved_quantity,
        ));
    }

    private function makeStore(string $name): Store
    {
        $user = User::factory()->create();
        $merchant = Merchant::create(['owner_user_id' => $user->id]);

        return $merchant->stores()->create([
            'name' => $name,
            'slug' => str($name)->slug().'-'.str()->random(8),
        ]);
    }
}