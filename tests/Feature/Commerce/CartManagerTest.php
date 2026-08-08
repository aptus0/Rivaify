<?php

namespace Tests\Feature\Commerce;

use App\Core\Tenancy\CurrentStore;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Commerce\Enums\Cart\CartStatus;
use Modules\Commerce\Enums\Catalog\ProductStatus;
use Modules\Commerce\Exceptions\Cart\CartItemNotPurchasableException;
use Modules\Commerce\Exceptions\Inventory\InsufficientInventoryException;
use Modules\Commerce\Models\Cart\Cart;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Models\Catalog\ProductVariant;
use Modules\Commerce\Services\Cart\CartManager;
use Modules\Commerce\Services\Inventory\InventoryManager;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Models\Store;
use Tests\TestCase;

class CartManagerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_guest_cart_is_persisted_and_reused_by_token(): void
    {
        $store = $this->makeStore('Cart Token Store');
        $this->setCurrentStore($store);
        $manager = app(CartManager::class);

        $cart = $manager->getOrCreate();
        $reusedCart = $manager->getOrCreate($cart->token);

        $this->assertSame($cart->id, $reusedCart->id);
        $this->assertNotEmpty($cart->ulid);
        $this->assertNotEmpty($cart->token);
        $this->assertNotNull($cart->expires_at);
        $this->assertDatabaseHas('carts', [
            'id' => $cart->id,
            'store_id' => $store->id,
            'status' => CartStatus::Active->value,
            'currency' => 'TRY',
        ]);
    }

    public function test_adding_an_item_uses_the_variant_database_price_and_recalculates_totals(): void
    {
        $store = $this->makeStore('Cart Price Store');
        $this->setCurrentStore($store);
        $variant = $this->makeActiveVariant('4499.95');
        $manager = app(CartManager::class);
        $cart = $manager->getOrCreate();

        $item = $manager->addItem($cart, $variant, 2);
        $cart = $cart->fresh();

        $this->assertSame('4499.95', $item->unit_price);
        $this->assertSame('8999.90', $item->line_total);
        $this->assertSame('8999.90', $cart->subtotal);
        $this->assertSame('8999.90', $cart->grand_total);
    }

    public function test_inactive_variants_cannot_be_added_to_a_cart(): void
    {
        $store = $this->makeStore('Inactive Variant Store');
        $this->setCurrentStore($store);
        $variant = $this->makeActiveVariant('99.00');
        $variant->update(['status' => ProductStatus::Draft]);

        $this->expectException(CartItemNotPurchasableException::class);

        app(CartManager::class)->addItem(app(CartManager::class)->getOrCreate(), $variant, 1);
    }

    public function test_variants_from_another_store_cannot_be_added_to_a_cart(): void
    {
        $storeA = $this->makeStore('Cart Store A');
        $storeB = $this->makeStore('Cart Store B');

        $this->setCurrentStore($storeB);
        $foreignVariant = $this->makeActiveVariant('99.00');

        $this->setCurrentStore($storeA);
        $cart = app(CartManager::class)->getOrCreate();

        $this->expectException(CartItemNotPurchasableException::class);

        app(CartManager::class)->addItem($cart, $foreignVariant, 1);
    }

    public function test_adding_the_same_variant_accumulates_quantity_and_recalculates_totals(): void
    {
        $store = $this->makeStore('Cart Quantity Store');
        $this->setCurrentStore($store);
        $variant = $this->makeActiveVariant('100.25');
        $manager = app(CartManager::class);
        $cart = $manager->getOrCreate();

        $manager->addItem($cart, $variant, 1);
        $item = $manager->addItem($cart, $variant, 2);

        $this->assertSame(3, $item->quantity);
        $this->assertSame('300.75', $cart->fresh()->grand_total);
        $this->assertSame(1, $cart->items()->count());
    }

    public function test_merging_carts_combines_matching_variants(): void
    {
        $store = $this->makeStore('Cart Merge Store');
        $this->setCurrentStore($store);
        $variant = $this->makeActiveVariant('25.50');
        $manager = app(CartManager::class);
        $guestCart = $manager->getOrCreate();
        $customerCart = $manager->getOrCreate();

        $manager->addItem($guestCart, $variant, 2);
        $manager->addItem($customerCart, $variant, 1);
        $mergedCart = $manager->merge($guestCart, $customerCart);

        $this->assertSame(CartStatus::Abandoned, $guestCart->fresh()->status);
        $this->assertSame(3, $mergedCart->items()->sole()->quantity);
        $this->assertSame('76.50', $mergedCart->grand_total);
    }

    public function test_merging_carts_rejects_quantity_above_tracked_sellable_inventory(): void
    {
        $store = $this->makeStore('Cart Merge Inventory Store');
        $this->setCurrentStore($store);
        $variant = $this->makeActiveVariant('25.50');
        $inventory = app(InventoryManager::class);
        $location = $inventory->createLocation('Karacabey Depo');
        $inventory->setAvailable($variant, $location, 2);
        $manager = app(CartManager::class);
        $guestCart = $manager->getOrCreate();
        $customerCart = $manager->getOrCreate();
        $manager->addItem($guestCart, $variant, 2);
        $manager->addItem($customerCart, $variant, 1);

        $this->expectException(InsufficientInventoryException::class);

        $manager->merge($guestCart, $customerCart);
    }

    private function makeStore(string $name): Store
    {
        $user = User::factory()->create();
        $merchant = Merchant::create(['owner_user_id' => $user->id]);

        return $merchant->stores()->create([
            'name' => $name,
            'slug' => str($name)->slug(),
        ]);
    }

    private function makeActiveVariant(string $price): ProductVariant
    {
        $product = Product::query()->create([
            'title' => 'Cart Product '.str()->random(8),
            'slug' => 'cart-product-'.str()->random(12),
            'status' => ProductStatus::Active,
        ]);

        return $product->variants()->create([
            'title' => 'Default',
            'price' => $price,
            'status' => ProductStatus::Active,
        ]);
    }

    private function setCurrentStore(Store $store): void
    {
        app(CurrentStore::class)->set($store);
    }
}