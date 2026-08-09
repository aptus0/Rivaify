<?php

namespace Tests\Feature\Commerce;

use App\Core\Tenancy\CurrentStore;
use App\Core\Tenancy\Scopes\StoreScope;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Modules\Commerce\DTOs\Catalog\ProductEditorData;
use Modules\Commerce\DTOs\Catalog\ProductVariantEditorData;
use Modules\Commerce\Enums\Catalog\BrandStatus;
use Modules\Commerce\Enums\Catalog\CategoryStatus;
use Modules\Commerce\Enums\Catalog\ProductStatus;
use Modules\Commerce\Models\Catalog\Brand;
use Modules\Commerce\Models\Catalog\Category;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Models\Inventory\InventoryLocation;
use Modules\Commerce\Services\Catalog\ProductCsvManager;
use Modules\Commerce\Services\Catalog\ProductEditor;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Enums\StoreUserRole;
use Modules\Store\Enums\StoreUserStatus;
use Modules\Store\Models\Store;
use Modules\Store\Models\StoreUser;
use Tests\TestCase;

class AdminProductCsvApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Referer', 'https://app.rivaify.com');
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_owner_can_preview_and_import_csv_through_product_editor_and_inventory_manager(): void
    {
        [$owner, $store] = $this->makeStoreWithUser('CSV Import Store');
        app(CurrentStore::class)->set($store);
        $category = Category::query()->create([
            'name' => 'Ayakkabı',
            'slug' => 'ayakkabi-'.str()->lower(str()->random(6)),
            'status' => CategoryStatus::Active,
        ]);
        $brand = Brand::query()->create([
            'name' => 'Riva',
            'slug' => 'riva-'.str()->lower(str()->random(6)),
            'status' => BrandStatus::Active,
        ]);
        $location = InventoryLocation::query()->create(['name' => 'Ana Depo', 'code' => 'CSVMAIN']);
        app(CurrentStore::class)->clear();
        Sanctum::actingAs($owner);

        $csv = $this->csv([[
            'handle' => 'csv-spor-ayakkabi',
            'title' => 'CSV Spor Ayakkabı',
            'description' => '<p>Güvenli açıklama</p><script>alert(1)</script>',
            'status' => 'active',
            'product_type' => 'physical',
            'vendor' => 'Riva',
            'category' => $category->slug,
            'brand' => $brand->slug,
            'tags' => 'Spor|Yeni',
            'is_taxable' => 'true',
            'requires_shipping' => 'true',
            'package_width' => '30.00',
            'package_height' => '12.00',
            'package_length' => '40.00',
            'package_dimension_unit' => 'cm',
            'variant_title' => 'Default',
            'variant_sku' => 'CSV-SKU-001',
            'variant_price' => '1499.90',
            'variant_cost_price' => '800.00',
            'variant_weight' => '1.200',
            'variant_weight_unit' => 'kg',
            'variant_status' => 'active',
            'variant_is_taxable' => 'true',
            'variant_requires_shipping' => 'true',
            'track_inventory' => 'true',
            'allow_oversell' => 'false',
            'inventory_location' => $location->code,
            'inventory_quantity' => '17',
        ]]);

        $this->withSession(['current_store_id' => $store->id])
            ->post('/api/v1/products/import', [
                'mode' => 'preview',
                'file' => UploadedFile::fake()->createWithContent('products.csv', $csv),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.mode', 'preview')
            ->assertJsonPath('data.row_count', 1)
            ->assertJsonPath('data.product_count', 1)
            ->assertJsonPath('data.will_create', 1)
            ->assertJsonPath('data.can_import', true)
            ->assertJsonPath('data.error_count', 0);

        $this->assertDatabaseMissing('products', ['store_id' => $store->id, 'slug' => 'csv-spor-ayakkabi']);

        $this->withSession(['current_store_id' => $store->id])
            ->post('/api/v1/products/import', [
                'mode' => 'commit',
                'file' => UploadedFile::fake()->createWithContent('products.csv', $csv),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.mode', 'commit')
            ->assertJsonPath('data.created', 1)
            ->assertJsonPath('data.updated', 0)
            ->assertJsonPath('data.failed', 0);

        app(CurrentStore::class)->set($store);
        $product = Product::query()
            ->with(['category', 'brand', 'tags', 'variants.inventoryItem.levels', 'variants.inventoryItem.movements'])
            ->where('slug', 'csv-spor-ayakkabi')
            ->sole();
        $variant = $product->variants->sole();
        $this->assertSame('CSV Spor Ayakkabı', $product->title);
        $this->assertStringNotContainsString('<script>', (string) $product->description);
        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame(['Spor', 'Yeni'], $product->tags->pluck('name')->sort()->values()->all());
        $this->assertSame('CSV-SKU-001', $variant->sku);
        $this->assertTrue($variant->inventoryItem->is_tracked);
        $this->assertSame(17, $variant->inventoryItem->levels->sole()->available_quantity);
        $this->assertSame('product_editor', $variant->inventoryItem->movements->sole()->reason);
    }

    public function test_import_rejects_bad_headers_and_reports_invalid_rows_without_writing(): void
    {
        [$owner, $store] = $this->makeStoreWithUser('CSV Validation Store');
        Sanctum::actingAs($owner);

        $this->withSession(['current_store_id' => $store->id])
            ->post('/api/v1/products/import', [
                'mode' => 'preview',
                'file' => UploadedFile::fake()->createWithContent('broken.csv', "title,price\nBroken,12\n"),
            ], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $invalid = $this->csv([[
            'handle' => 'invalid-product',
            'title' => 'Invalid Product',
            'status' => 'published',
            'product_type' => 'physical',
            'is_taxable' => 'maybe',
            'requires_shipping' => 'true',
            'package_dimension_unit' => 'cm',
            'variant_price' => '-5',
            'variant_weight_unit' => 'kg',
            'variant_status' => 'draft',
            'variant_is_taxable' => 'true',
            'variant_requires_shipping' => 'true',
            'track_inventory' => 'false',
            'allow_oversell' => 'false',
        ]]);

        $response = $this->withSession(['current_store_id' => $store->id])
            ->post('/api/v1/products/import', [
                'mode' => 'commit',
                'file' => UploadedFile::fake()->createWithContent('invalid.csv', $invalid),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.can_import', false)
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.updated', 0);

        $fields = collect($response->json('data.errors'))->pluck('field');
        $this->assertTrue($fields->contains('status'));
        $this->assertTrue($fields->contains('is_taxable'));
        $this->assertTrue($fields->contains('variant_price'));
        $this->assertDatabaseMissing('products', ['store_id' => $store->id, 'slug' => 'invalid-product']);
    }

    public function test_export_and_update_import_are_tenant_scoped(): void
    {
        [$ownerA, $storeA] = $this->makeStoreWithUser('CSV Tenant A');
        [, $storeB] = $this->makeStoreWithUser('CSV Tenant B');
        app(CurrentStore::class)->set($storeA);
        $activeA = $this->createSimpleProduct('Tenant A Active', 'tenant-a-active', 'TENANT-A-ACTIVE', ProductStatus::Active);
        $this->createSimpleProduct('Tenant A Draft', 'tenant-a-draft', 'TENANT-A-DRAFT', ProductStatus::Draft);
        app(CurrentStore::class)->set($storeB);
        $foreign = $this->createSimpleProduct('Tenant B Secret', 'tenant-b-secret', 'TENANT-B-SECRET', ProductStatus::Active);
        app(CurrentStore::class)->clear();
        Sanctum::actingAs($ownerA);

        $export = $this->withSession(['current_store_id' => $storeA->id])
            ->get('/api/v1/products/export?status=active', ['Accept' => 'text/csv'])
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $export->streamedContent();
        $this->assertStringContainsString('Tenant A Active', $content);
        $this->assertStringContainsString($activeA->ulid, $content);
        $this->assertStringNotContainsString('Tenant A Draft', $content);
        $this->assertStringNotContainsString('Tenant B Secret', $content);
        $this->assertStringNotContainsString('TENANT-B-SECRET', $content);

        $this->withSession(['current_store_id' => $storeA->id])
            ->post('/api/v1/products/import', [
                'mode' => 'preview',
                'file' => UploadedFile::fake()->createWithContent('roundtrip.csv', $content),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.can_import', true)
            ->assertJsonPath('data.will_create', 0)
            ->assertJsonPath('data.will_update', 1);

        $foreignUpdate = $this->csv([[
            'product_id' => $foreign->ulid,
            'handle' => 'tenant-b-secret',
            'title' => 'Attempted Cross Tenant Update',
            'status' => 'active',
            'product_type' => 'physical',
            'is_taxable' => 'true',
            'requires_shipping' => 'true',
            'package_dimension_unit' => 'cm',
            'variant_title' => 'Default',
            'variant_sku' => 'TENANT-B-SECRET',
            'variant_price' => '100.00',
            'variant_weight_unit' => 'kg',
            'variant_status' => 'active',
            'variant_is_taxable' => 'true',
            'variant_requires_shipping' => 'true',
            'track_inventory' => 'false',
            'allow_oversell' => 'false',
        ]]);
        $response = $this->withSession(['current_store_id' => $storeA->id])
            ->post('/api/v1/products/import', [
                'mode' => 'commit',
                'file' => UploadedFile::fake()->createWithContent('foreign.csv', $foreignUpdate),
            ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.can_import', false)
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.updated', 0);
        $this->assertSame('product_id', $response->json('data.errors.0.field'));

        $this->assertDatabaseHas('products', [
            'id' => $foreign->id,
            'store_id' => $storeB->id,
            'title' => 'Tenant B Secret',
        ]);
    }

    private function createSimpleProduct(string $title, string $slug, string $sku, ProductStatus $status): Product
    {
        return app(ProductEditor::class)->create(new ProductEditorData(
            title: $title,
            slug: $slug,
            status: $status,
            variants: [new ProductVariantEditorData(
                title: 'Default',
                price: '100.00',
                sku: $sku,
                status: $status,
                trackInventory: false,
            )],
        ));
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     */
    private function csv(array $rows): string
    {
        $stream = fopen('php://temp', 'w+b');
        fputcsv($stream, ProductCsvManager::HEADERS, ',', '"', '');
        foreach ($rows as $values) {
            $row = array_map(fn (string $header): string => $values[$header] ?? '', ProductCsvManager::HEADERS);
            fputcsv($stream, $row, ',', '"', '');
        }
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        return $contents === false ? '' : $contents;
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function makeStoreWithUser(string $name): array
    {
        $user = User::factory()->create();
        $merchant = Merchant::query()->create(['owner_user_id' => $user->id]);
        $store = $merchant->stores()->create([
            'name' => $name,
            'slug' => str($name)->slug().'-'.str()->lower(str()->random(8)),
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
