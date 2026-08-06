<?php

namespace Tests\Feature\Commerce;

use App\Core\Tenancy\CurrentStore;
use App\Core\Tenancy\Scopes\StoreScope;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Commerce\Actions\Catalog\AssignProductCategory;
use Modules\Commerce\Actions\Catalog\CreateBrand;
use Modules\Commerce\Actions\Catalog\CreateCategory;
use Modules\Commerce\Actions\Catalog\CreateProduct;
use Modules\Commerce\DTOs\Catalog\CreateBrandData;
use Modules\Commerce\DTOs\Catalog\CreateCategoryData;
use Modules\Commerce\DTOs\Catalog\CreateProductData;
use Modules\Commerce\Exceptions\Catalog\CrossStoreAssignmentException;
use Modules\Commerce\Models\Catalog\Brand;
use Modules\Commerce\Models\Catalog\Category;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Enums\StoreUserRole;
use Modules\Store\Enums\StoreUserStatus;
use Modules\Store\Models\Store;
use Modules\Store\Models\StoreUser;
use Tests\TestCase;

/**
 * Sprint 02 build order step 2 (brief): category + brand, plus wiring them
 * onto products. Tenant isolation for these is the same invariant as
 * products/variants (brief §34) — nested resources must belong to the
 * current store, enforced here via CrossStoreAssignmentException rather
 * than a silently-empty result.
 */
class CategoryAndBrandTest extends TestCase
{
    use DatabaseTransactions;

    private function makeStore(string $name): Store
    {
        $user = User::factory()->create();
        $merchant = Merchant::create(['owner_user_id' => $user->id]);
        $store = $merchant->stores()->create(['name' => $name, 'slug' => str($name)->slug()]);
        StoreUser::withoutGlobalScope(StoreScope::class)->create([
            'store_id' => $store->id,
            'user_id' => $user->id,
            'role' => StoreUserRole::Owner,
            'status' => StoreUserStatus::Active,
            'joined_at' => now(),
        ]);

        return $store;
    }

    public function test_category_tree_can_be_built_with_parent_child_relations(): void
    {
        app(CurrentStore::class)->set($this->makeStore('Karacabey Store'));

        $giyim = (new CreateCategory)->handle(new CreateCategoryData(name: 'Giyim'));
        $erkek = (new CreateCategory)->handle(new CreateCategoryData(name: 'Erkek', parentId: $giyim->id));
        $ayakkabi = (new CreateCategory)->handle(new CreateCategoryData(name: 'Ayakkabı', parentId: $erkek->id));

        $this->assertNull($giyim->parent);
        $this->assertTrue($giyim->children->contains('id', $erkek->id));
        $this->assertSame($giyim->id, $erkek->parent->id);
        $this->assertSame($erkek->id, $ayakkabi->parent->id);
    }

    public function test_category_slug_is_unique_per_store_but_can_repeat_across_stores(): void
    {
        app(CurrentStore::class)->set($this->makeStore('Store A'));
        $categoryA = (new CreateCategory)->handle(new CreateCategoryData(name: 'Ayakkabı'));
        $this->assertSame('ayakkabi', $categoryA->slug);

        $duplicate = (new CreateCategory)->handle(new CreateCategoryData(name: 'Ayakkabı'));
        $this->assertSame('ayakkabi-2', $duplicate->slug);

        app(CurrentStore::class)->set($this->makeStore('Store B'));
        $categoryB = (new CreateCategory)->handle(new CreateCategoryData(name: 'Ayakkabı'));
        $this->assertSame('ayakkabi', $categoryB->slug);
    }

    public function test_store_a_cannot_see_store_bs_categories_or_brands(): void
    {
        $currentStore = app(CurrentStore::class);

        $currentStore->set($this->makeStore('Store A'));
        (new CreateCategory)->handle(new CreateCategoryData(name: 'Store A Category'));
        (new CreateBrand)->handle(new CreateBrandData(name: 'Store A Brand'));

        $currentStore->set($this->makeStore('Store B'));
        (new CreateCategory)->handle(new CreateCategoryData(name: 'Store B Category'));
        (new CreateBrand)->handle(new CreateBrandData(name: 'Store B Brand'));

        $this->assertSame('Store B Category', Category::query()->sole()->name);
        $this->assertSame('Store B Brand', Brand::query()->sole()->name);
    }

    public function test_creating_a_category_under_another_stores_parent_is_rejected(): void
    {
        $currentStore = app(CurrentStore::class);

        $currentStore->set($this->makeStore('Store A'));
        $foreignParent = (new CreateCategory)->handle(new CreateCategoryData(name: 'Foreign Parent'));

        $currentStore->set($this->makeStore('Store B'));

        $this->expectException(CrossStoreAssignmentException::class);
        (new CreateCategory)->handle(new CreateCategoryData(name: 'Child', parentId: $foreignParent->id));
    }

    public function test_product_can_be_created_with_a_category_and_brand_from_the_same_store(): void
    {
        app(CurrentStore::class)->set($this->makeStore('Karacabey Store'));

        $category = (new CreateCategory)->handle(new CreateCategoryData(name: 'Ayakkabı'));
        $brand = (new CreateBrand)->handle(new CreateBrandData(name: 'Nike'));

        $product = (new CreateProduct)->handle(new CreateProductData(
            title: 'Nike Air Max',
            categoryId: $category->id,
            brandId: $brand->id,
        ));

        $this->assertSame($category->id, $product->category_id);
        $this->assertSame($brand->id, $product->brand_id);
        $this->assertSame('Ayakkabı', $product->category->name);
        $this->assertSame('Nike', $product->brand->name);
    }

    public function test_product_cannot_be_created_with_a_category_from_another_store(): void
    {
        $currentStore = app(CurrentStore::class);

        $currentStore->set($this->makeStore('Store A'));
        $foreignCategory = (new CreateCategory)->handle(new CreateCategoryData(name: 'Store A Category'));

        $currentStore->set($this->makeStore('Store B'));

        $this->expectException(CrossStoreAssignmentException::class);
        (new CreateProduct)->handle(new CreateProductData(title: 'Product', categoryId: $foreignCategory->id));
    }

    public function test_assign_product_category_updates_and_rejects_foreign_categories(): void
    {
        $currentStore = app(CurrentStore::class);

        $currentStore->set($this->makeStore('Store A'));
        $product = (new CreateProduct)->handle(new CreateProductData(title: 'Product'));
        $category = (new CreateCategory)->handle(new CreateCategoryData(name: 'Category'));

        $updated = (new AssignProductCategory)->handle($product, $category->id);
        $this->assertSame($category->id, $updated->category_id);

        $cleared = (new AssignProductCategory)->handle($product, null);
        $this->assertNull($cleared->category_id);

        $currentStore->set($this->makeStore('Store B'));
        $foreignCategory = (new CreateCategory)->handle(new CreateCategoryData(name: 'Foreign'));

        $currentStore->set($this->makeStore('Store C'));
        $otherProduct = (new CreateProduct)->handle(new CreateProductData(title: 'Other Product'));

        $this->expectException(CrossStoreAssignmentException::class);
        (new AssignProductCategory)->handle($otherProduct, $foreignCategory->id);
    }
}
