<?php

namespace Tests\Feature\Commerce;

use App\Core\Tenancy\CurrentStore;
use App\Core\Tenancy\Scopes\StoreScope;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Commerce\Actions\Catalog\CreateProduct;
use Modules\Commerce\Actions\Catalog\GenerateProductVariants;
use Modules\Commerce\DTOs\Catalog\CreateProductData;
use Modules\Commerce\DTOs\Catalog\ProductOptionInputData;
use Modules\Commerce\Exceptions\Catalog\InvalidProductOptionsException;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Enums\StoreUserRole;
use Modules\Store\Enums\StoreUserStatus;
use Modules\Store\Models\Store;
use Modules\Store\Models\StoreUser;
use Tests\TestCase;

/**
 * Brief §5/§7/§8/§29: the variant generator is called out as one of the
 * highest-risk pieces of Sprint 02, alongside inventory and tenant
 * isolation — so it gets its own focused coverage.
 */
class ProductVariantGenerationTest extends TestCase
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

    private function createProduct(string $title = 'Nike Air Max'): Product
    {
        return (new CreateProduct)->handle(new CreateProductData(title: $title));
    }

    public function test_new_product_gets_a_single_default_variant(): void
    {
        app(CurrentStore::class)->set($this->makeStore('Karacabey Store'));

        $product = $this->createProduct();

        $this->assertCount(1, $product->variants);
        $this->assertSame('Default', $product->variants->first()->title);
    }

    public function test_generating_variants_from_two_options_produces_the_cartesian_product(): void
    {
        app(CurrentStore::class)->set($this->makeStore('Karacabey Store'));
        $product = $this->createProduct();

        $variants = (new GenerateProductVariants)->handle($product, [
            new ProductOptionInputData('Color', ['Black', 'White']),
            new ProductOptionInputData('Size', ['S', 'M']),
        ]);

        $this->assertCount(4, $variants);
        $this->assertEqualsCanonicalizing(
            ['Black / S', 'Black / M', 'White / S', 'White / M'],
            $variants->pluck('title')->all()
        );
    }

    public function test_generating_variants_replaces_the_auto_created_default_variant(): void
    {
        app(CurrentStore::class)->set($this->makeStore('Karacabey Store'));
        $product = $this->createProduct();

        (new GenerateProductVariants)->handle($product, [
            new ProductOptionInputData('Size', ['S', 'M']),
        ]);

        $titles = $product->variants()->pluck('title')->all();
        $this->assertNotContains('Default', $titles);
        $this->assertCount(2, $titles);
    }

    public function test_regenerating_variants_preserves_price_and_sku_on_unchanged_combinations(): void
    {
        app(CurrentStore::class)->set($this->makeStore('Karacabey Store'));
        $product = $this->createProduct();

        $variants = (new GenerateProductVariants)->handle($product, [
            new ProductOptionInputData('Color', ['Black', 'White']),
        ]);

        $black = $variants->firstWhere('title', 'Black');
        $black->update(['sku' => 'NK-AM-BLK', 'price' => 4499]);

        $regenerated = (new GenerateProductVariants)->handle($product, [
            new ProductOptionInputData('Color', ['Black', 'White', 'Red']),
        ]);

        $blackAfter = $regenerated->firstWhere('title', 'Black');
        $this->assertSame($black->id, $blackAfter->id);
        $this->assertSame('NK-AM-BLK', $blackAfter->sku);
        $this->assertEquals(4499, $blackAfter->price);
        $this->assertCount(3, $regenerated);
    }

    public function test_shrinking_option_values_removes_the_dropped_variants(): void
    {
        app(CurrentStore::class)->set($this->makeStore('Karacabey Store'));
        $product = $this->createProduct();

        (new GenerateProductVariants)->handle($product, [
            new ProductOptionInputData('Size', ['S', 'M', 'L']),
        ]);

        $remaining = (new GenerateProductVariants)->handle($product, [
            new ProductOptionInputData('Size', ['S']),
        ]);

        $this->assertCount(1, $remaining);
        $this->assertSame('S', $remaining->first()->title);
    }

    public function test_empty_options_are_rejected(): void
    {
        app(CurrentStore::class)->set($this->makeStore('Karacabey Store'));
        $product = $this->createProduct();

        $this->expectException(InvalidProductOptionsException::class);

        (new GenerateProductVariants)->handle($product, [
            new ProductOptionInputData('Size', []),
        ]);
    }

    public function test_duplicate_option_names_are_rejected(): void
    {
        app(CurrentStore::class)->set($this->makeStore('Karacabey Store'));
        $product = $this->createProduct();

        $this->expectException(InvalidProductOptionsException::class);

        (new GenerateProductVariants)->handle($product, [
            new ProductOptionInputData('Size', ['S']),
            new ProductOptionInputData('Size', ['M']),
        ]);
    }

    public function test_sku_must_be_unique_within_a_store(): void
    {
        app(CurrentStore::class)->set($this->makeStore('Store A'));
        $productA = $this->createProduct('Product A');
        $productB = $this->createProduct('Product B');

        $productA->variants->first()->update(['sku' => 'ABC123']);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $productB->variants->first()->update(['sku' => 'ABC123']);
    }

    public function test_sku_can_repeat_across_different_stores(): void
    {
        app(CurrentStore::class)->set($this->makeStore('Store A'));
        $productA = $this->createProduct('Product A');
        $productA->variants->first()->update(['sku' => 'ABC123']);

        app(CurrentStore::class)->set($this->makeStore('Store B'));
        $productB = $this->createProduct('Product B');
        $productB->variants->first()->update(['sku' => 'ABC123']);

        $this->assertSame('ABC123', $productB->variants->first()->fresh()->sku);
    }
}
