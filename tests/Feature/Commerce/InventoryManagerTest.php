<?php

namespace Tests\Feature\Commerce;

use App\Core\Tenancy\CurrentStore;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Commerce\Enums\Catalog\ProductStatus;
use Modules\Commerce\Enums\Inventory\InventoryReservationStatus;
use Modules\Commerce\Exceptions\Inventory\InsufficientInventoryException;
use Modules\Commerce\Models\Cart\Cart;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Models\Catalog\ProductVariant;
use Modules\Commerce\Models\Checkout\CheckoutSession;
use Modules\Commerce\Services\Cart\CartManager;
use Modules\Commerce\Services\Checkout\CheckoutManager;
use Modules\Commerce\Services\Inventory\InventoryManager;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Models\Store;
use Tests\TestCase;

class InventoryManagerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_reservation_blocks_requests_above_sellable_inventory(): void
    {
        $store = $this->makeStore('Inventory Reservation Store');
        $this->setCurrentStore($store);
        $variant = $this->makeVariant();
        $manager = app(InventoryManager::class);
        $location = $manager->createLocation('Karacabey Depo', 'KRC');
        $level = $manager->setAvailable($variant, $location, 10);
        $firstCheckout = $this->checkoutForVariant($variant, 8);
        $secondCheckout = $this->checkoutForVariant($variant, 3);

        $reservations = $manager->reserveForCheckout($firstCheckout);

        $this->assertCount(1, $reservations);
        $this->assertSame(8, $level->fresh()->reserved_quantity);
        $this->expectException(InsufficientInventoryException::class);

        $manager->reserveForCheckout($secondCheckout);
    }

    public function test_committing_reservation_decrements_available_stock_and_clears_reserved_stock(): void
    {
        $store = $this->makeStore('Inventory Commit Store');
        $this->setCurrentStore($store);
        $variant = $this->makeVariant();
        $manager = app(InventoryManager::class);
        $location = $manager->createLocation('Karacabey Depo');
        $level = $manager->setAvailable($variant, $location, 10);
        $checkout = $this->checkoutForVariant($variant, 2);

        $manager->reserveForCheckout($checkout);
        $reservation = $manager->commitForCheckout($checkout)->sole();

        $this->assertSame(8, $level->fresh()->available_quantity);
        $this->assertSame(0, $level->fresh()->reserved_quantity);
        $this->assertSame(InventoryReservationStatus::Committed, $reservation->status);
        $this->assertNotNull($reservation->committed_at);
    }

    public function test_releasing_reservation_restores_sellable_inventory_without_changing_available_stock(): void
    {
        $store = $this->makeStore('Inventory Release Store');
        $this->setCurrentStore($store);
        $variant = $this->makeVariant();
        $manager = app(InventoryManager::class);
        $location = $manager->createLocation('Karacabey Depo');
        $level = $manager->setAvailable($variant, $location, 10);
        $checkout = $this->checkoutForVariant($variant, 2);

        $manager->reserveForCheckout($checkout);
        $reservation = $manager->releaseForCheckout($checkout)->sole();

        $this->assertSame(10, $level->fresh()->available_quantity);
        $this->assertSame(0, $level->fresh()->reserved_quantity);
        $this->assertSame(InventoryReservationStatus::Released, $reservation->status);
        $this->assertNotNull($reservation->released_at);
    }

    public function test_expired_reservations_are_released_by_the_expiration_sweep(): void
    {
        $store = $this->makeStore('Inventory Expiration Store');
        $this->setCurrentStore($store);
        $variant = $this->makeVariant();
        $manager = app(InventoryManager::class);
        $location = $manager->createLocation('Karacabey Depo');
        $level = $manager->setAvailable($variant, $location, 1);
        $checkout = $this->checkoutForVariant($variant, 1);

        $reservation = $manager->reserveForCheckout($checkout, 1)->sole();
        $reservation->update(['expires_at' => now()->subMinute()]);

        $released = $manager->releaseExpired();

        $this->assertSame(1, $released);
        $this->assertSame(0, $level->fresh()->reserved_quantity);
        $this->assertSame(InventoryReservationStatus::Expired, $reservation->fresh()->status);
    }

    public function test_one_unit_stock_allows_only_one_of_two_checkout_reservations(): void
    {
        $store = $this->makeStore('Inventory Concurrency Store');
        $this->setCurrentStore($store);
        $variant = $this->makeVariant();
        $manager = app(InventoryManager::class);
        $location = $manager->createLocation('Karacabey Depo');
        $level = $manager->setAvailable($variant, $location, 1);
        $firstCheckout = $this->checkoutForVariant($variant, 1);
        $secondCheckout = $this->checkoutForVariant($variant, 1);

        $manager->reserveForCheckout($firstCheckout);
        try {
            $manager->reserveForCheckout($secondCheckout);
            $this->fail('Second reservation should be rejected when no sellable stock remains.');
        } catch (InsufficientInventoryException) {
            // The level lock and sellable check leave stock non-negative.
        }

        $this->assertSame(1, $level->fresh()->reserved_quantity);
        $this->assertSame(0, $level->fresh()->sellableQuantity());
        $this->assertGreaterThanOrEqual(0, $level->fresh()->available_quantity);
    }

    private function makeVariant(): ProductVariant
    {
        $product = Product::query()->create([
            'title' => 'Inventory Product '.str()->random(8),
            'slug' => 'inventory-product-'.str()->random(12),
            'status' => ProductStatus::Active,
        ]);

        return $product->variants()->create([
            'title' => 'Default',
            'price' => '100.00',
            'status' => ProductStatus::Active,
        ]);
    }

    private function checkoutForVariant(ProductVariant $variant, int $quantity): CheckoutSession
    {
        $cartManager = app(CartManager::class);
        $cart = $cartManager->getOrCreate();
        $cartManager->addItem($cart, $variant, $quantity);

        return app(CheckoutManager::class)->start($cart);
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

    private function setCurrentStore(Store $store): void
    {
        app(CurrentStore::class)->set($store);
    }
}