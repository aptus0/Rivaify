<?php

namespace Tests\Feature\Commerce;

use App\Core\Tenancy\CurrentStore;
use App\Core\Tenancy\Exceptions\StoreContextMissingException;
use App\Core\Tenancy\Scopes\StoreScope;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Commerce\Actions\Catalog\CreateProduct;
use Modules\Commerce\DTOs\Catalog\CreateProductData;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Enums\StoreUserRole;
use Modules\Store\Enums\StoreUserStatus;
use Modules\Store\Models\Store;
use Modules\Store\Models\StoreUser;
use Tests\TestCase;

/**
 * Same invariant as tests/Feature/TenantIsolationTest.php (brief §34): no
 * Commerce query may cross a store boundary, whether the model is queried
 * directly (Product) or through a child relation (variants).
 */
class ProductTenantIsolationTest extends TestCase
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

    public function test_product_query_without_current_store_throws(): void
    {
        $this->expectException(StoreContextMissingException::class);

        Product::query()->get();
    }

    public function test_store_a_cannot_see_store_bs_products(): void
    {
        $currentStore = app(CurrentStore::class);

        $currentStore->set($this->makeStore('Store A'));
        (new CreateProduct)->handle(new CreateProductData(title: 'Store A Product'));

        $currentStore->set($this->makeStore('Store B'));
        (new CreateProduct)->handle(new CreateProductData(title: 'Store B Product'));

        $visibleToB = Product::query()->get();

        $this->assertCount(1, $visibleToB);
        $this->assertSame('Store B Product', $visibleToB->first()->title);
    }

    public function test_variants_are_also_scoped_to_the_current_store(): void
    {
        $currentStore = app(CurrentStore::class);

        $currentStore->set($this->makeStore('Store A'));
        $productA = (new CreateProduct)->handle(new CreateProductData(title: 'Store A Product'));

        $currentStore->set($this->makeStore('Store B'));
        (new CreateProduct)->handle(new CreateProductData(title: 'Store B Product'));

        $this->assertSame(1, \Modules\Commerce\Models\Catalog\ProductVariant::query()->count());
        $this->assertNotSame($productA->store_id, $currentStore->id());
    }
}
