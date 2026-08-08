<?php

namespace Tests\Feature\Commerce;

use App\Core\Tenancy\CurrentStore;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Modules\Commerce\Enums\Catalog\ProductStatus;
use Modules\Commerce\Enums\Inventory\InventoryReservationStatus;
use Modules\Commerce\Exceptions\Inventory\InsufficientInventoryException;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Models\Catalog\ProductVariant;
use Modules\Commerce\Models\Checkout\CheckoutSession;
use Modules\Commerce\Models\Inventory\InventoryLevel;
use Modules\Commerce\Models\Inventory\InventoryReservation;
use Modules\Commerce\Services\Cart\CartManager;
use Modules\Commerce\Services\Checkout\CheckoutManager;
use Modules\Commerce\Services\Inventory\InventoryManager;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Models\Store;
use Tests\TestCase;

class InventoryConcurrencyTest extends TestCase
{
    private ?int $storeId = null;

    private ?int $merchantId = null;

    private ?int $userId = null;

    protected function tearDown(): void
    {
        app(CurrentStore::class)->clear();

        if ($this->storeId !== null) {
            Store::query()->whereKey($this->storeId)->delete();
        }
        if ($this->merchantId !== null) {
            Merchant::query()->whereKey($this->merchantId)->delete();
        }
        if ($this->userId !== null) {
            User::query()->whereKey($this->userId)->delete();
        }

        parent::tearDown();
    }

    public function test_single_stock_unit_allows_exactly_one_concurrent_reservation(): void
    {
        if (! function_exists('pcntl_fork')) {
            $this->markTestSkipped('pcntl is required for the inventory concurrency test.');
        }

        $store = $this->makeStore();
        app(CurrentStore::class)->set($store);
        $product = Product::query()->create([
            'title' => 'Concurrency Product',
            'slug' => 'concurrency-product-'.str()->random(10),
            'status' => ProductStatus::Active,
        ]);
        $variant = $product->variants()->create([
            'title' => 'Default',
            'price' => '100.00',
            'status' => ProductStatus::Active,
        ]);
        $inventory = app(InventoryManager::class);
        $location = $inventory->createLocation('Race Test Depo');
        $level = $inventory->setAvailable($variant, $location, 1);
        $firstCheckout = $this->checkoutForVariant($variant->id);
        $secondCheckout = $this->checkoutForVariant($variant->id);

        [$parentA, $childA] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        [$parentB, $childB] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $firstPid = $this->forkReservation($parentA, $childA, $store->id, $firstCheckout->id);
        $secondPid = $this->forkReservation($parentB, $childB, $store->id, $secondCheckout->id);

        fwrite($parentA, 'go');
        fwrite($parentB, 'go');
        fclose($parentA);
        fclose($parentB);

        pcntl_waitpid($firstPid, $firstStatus);
        pcntl_waitpid($secondPid, $secondStatus);
        DB::purge();
        DB::reconnect();
        app(CurrentStore::class)->set($store->fresh());

        $exitCodes = [pcntl_wexitstatus($firstStatus), pcntl_wexitstatus($secondStatus)];
        sort($exitCodes);

        $this->assertSame([0, 2], $exitCodes);
        $this->assertSame(1, $level->fresh()->available_quantity);
        $this->assertSame(1, $level->fresh()->reserved_quantity);
        $this->assertSame(0, $level->fresh()->sellableQuantity());
        $this->assertSame(1, InventoryReservation::query()
            ->where('status', InventoryReservationStatus::Active->value)
            ->count());
    }

    private function forkReservation($parentSocket, $childSocket, int $storeId, int $checkoutId): int
    {
        $pid = pcntl_fork();
        if ($pid === -1) {
            $this->fail('Unable to fork an inventory reservation child process.');
        }
        if ($pid !== 0) {
            fclose($childSocket);

            return $pid;
        }

        fclose($parentSocket);
        fread($childSocket, 2);
        fclose($childSocket);

        try {
            DB::purge();
            DB::reconnect();
            $store = Store::query()->findOrFail($storeId);
            app(CurrentStore::class)->set($store);
            $checkout = CheckoutSession::query()->findOrFail($checkoutId);
            app(InventoryManager::class)->reserveForCheckout($checkout);
            exit(0);
        } catch (InsufficientInventoryException) {
            exit(2);
        } catch (\Throwable) {
            exit(1);
        }
    }

    private function checkoutForVariant(int $variantId): CheckoutSession
    {
        $cartManager = app(CartManager::class);
        $cart = $cartManager->getOrCreate();
        $variant = ProductVariant::query()->findOrFail($variantId);
        $cartManager->addItem($cart, $variant, 1);

        return app(CheckoutManager::class)->start($cart);
    }

    private function makeStore(): Store
    {
        $user = User::factory()->create();
        $merchant = Merchant::create(['owner_user_id' => $user->id]);
        $store = $merchant->stores()->create([
            'name' => 'Concurrency Store '.str()->random(6),
            'slug' => 'concurrency-store-'.str()->random(10),
        ]);
        $this->userId = $user->id;
        $this->merchantId = $merchant->id;
        $this->storeId = $store->id;

        return $store;
    }
}