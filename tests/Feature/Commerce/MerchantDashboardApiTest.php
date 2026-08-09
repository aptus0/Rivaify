<?php

namespace Tests\Feature\Commerce;

use App\Core\Tenancy\CurrentStore;
use App\Core\Tenancy\Scopes\StoreScope;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use Modules\Commerce\Enums\Catalog\ProductStatus;
use Modules\Commerce\Enums\Order\PaymentStatus as OrderPaymentStatus;
use Modules\Commerce\Enums\Payment\PaymentStatus as GatewayPaymentStatus;
use Modules\Commerce\Enums\Payment\PaymentTransactionStatus;
use Modules\Commerce\Enums\Payment\PaymentTransactionType;
use Modules\Commerce\Events\Inventory\InventoryAdjusted;
use Modules\Commerce\Events\Order\OrderPlaced;
use Modules\Commerce\Listeners\InvalidateMerchantDashboardCache;
use Modules\Commerce\Models\Cart\Cart;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Models\Checkout\CheckoutSession;
use Modules\Commerce\Models\Customer\Customer;
use Modules\Commerce\Models\Order\Order;
use Modules\Commerce\Models\Payment\Payment;
use Modules\Commerce\Services\Inventory\InventoryManager;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Enums\StoreUserRole;
use Modules\Store\Enums\StoreUserStatus;
use Modules\Store\Models\Store;
use Modules\Store\Models\StoreUser;
use Tests\TestCase;

class MerchantDashboardApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-08 12:00:00', 'Europe/Istanbul'));
        $this->withHeader('Referer', 'https://app.rivaify.com');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        app(CurrentStore::class)->clear();

        parent::tearDown();
    }

    public function test_dashboard_returns_store_scoped_period_changes_customer_summary_and_failed_payments(): void
    {
        [$owner, $store] = $this->makeStoreWithUser('Dashboard Production Store');
        [, $foreignStore] = $this->makeStoreWithUser('Dashboard Foreign Store');

        app(CurrentStore::class)->set($store);
        $returningCustomer = $this->createCustomer('returning@example.com', '2026-07-30 10:00:00');
        $newCustomer = $this->createCustomer('new@example.com', '2026-08-03 10:00:00');
        $this->createCustomer('new-without-order@example.com', '2026-08-04 10:00:00');
        $previousOrder = $this->createOrder(
            'RV-PREVIOUS',
            '100.00',
            '2026-07-30 12:00:00',
            $returningCustomer,
        );
        $this->createOrder('RV-CURRENT-1', '150.00', '2026-08-03 12:00:00', $returningCustomer);
        $recentOrder = $this->createOrder('RV-CURRENT-2', '150.00', '2026-08-05 12:00:00', $newCustomer);
        $this->createRefund($recentOrder, '25.00');
        $this->createFailedPayment();

        app(CurrentStore::class)->set($foreignStore);
        $foreignCustomer = $this->createCustomer('foreign@example.com', '2026-08-04 10:00:00');
        $this->createOrder('RV-FOREIGN', '999.00', '2026-08-05 12:00:00', $foreignCustomer);

        app(CurrentStore::class)->clear();
        Sanctum::actingAs($owner);
        $response = $this->withSession(['current_store_id' => $store->id])
            ->getJson('/api/v1/dashboard?range=7d')
            ->assertOk()
            ->assertJsonPath('data.range', '7d')
            ->assertJsonPath('data.sales', '275.00')
            ->assertJsonPath('data.refunds', '25.00')
            ->assertJsonPath('data.orders', 2)
            ->assertJsonPath('data.average_order', '137.50')
            ->assertJsonPath('data.customers', 2)
            ->assertJsonPath('data.previous_period.sales', '100.00')
            ->assertJsonPath('data.previous_period.refunds', '0.00')
            ->assertJsonPath('data.previous_period.orders', 1)
            ->assertJsonPath('data.previous_period.average_order', '100.00')
            ->assertJsonPath('data.previous_period.customers', 1)
            ->assertJsonPath('data.customer_summary.total_customers', 3)
            ->assertJsonPath('data.customer_summary.new_customers', 2)
            ->assertJsonPath('data.customer_summary.previous_new_customers', 1)
            ->assertJsonPath('data.customer_summary.purchasing_customers', 2)
            ->assertJsonPath('data.customer_summary.returning_customers', 1)
            ->assertJsonPath('data.order_status.failed_payments', 1)
            ->assertJsonPath('data.recent_orders.0.id', $recentOrder->ulid)
            ->assertJsonMissing(['number' => 'RV-FOREIGN']);

        $this->assertEquals(175.0, $response->json('data.changes.sales'));
        $this->assertEquals(100.0, $response->json('data.changes.orders'));
        $this->assertEquals(37.5, $response->json('data.changes.average_order'));
        $this->assertEquals(100.0, $response->json('data.changes.customers'));
        $this->assertEquals(50.0, $response->json('data.customer_summary.returning_rate'));
        $this->assertSame('Europe/Istanbul', $response->json('data.period.timezone'));
        $this->assertSame($previousOrder->ulid, $response->json('data.recent_orders.2.id'));
    }

    public function test_dashboard_cache_is_invalidated_for_only_the_event_store(): void
    {
        [$owner, $store] = $this->makeStoreWithUser('Dashboard Cache Store');
        app(CurrentStore::class)->set($store);
        $this->createOrder('RV-CACHE-1', '100.00', '2026-08-08 10:00:00');
        app(CurrentStore::class)->clear();
        Sanctum::actingAs($owner);

        $this->withSession(['current_store_id' => $store->id])
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.sales', '100.00');

        app(CurrentStore::class)->set($store);
        $newOrder = $this->createOrder('RV-CACHE-2', '50.00', '2026-08-08 11:00:00');
        app(CurrentStore::class)->clear();

        $this->withSession(['current_store_id' => $store->id])
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.sales', '100.00');

        $listener = app(InvalidateMerchantDashboardCache::class);
        $this->assertInstanceOf(ShouldHandleEventsAfterCommit::class, $listener);
        $listener->handle(new OrderPlaced($newOrder));

        $this->withSession(['current_store_id' => $store->id])
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.sales', '150.00');
    }

    public function test_inventory_manager_dispatches_inventory_adjusted_with_audit_context(): void
    {
        [, $store] = $this->makeStoreWithUser('Dashboard Inventory Event Store');
        app(CurrentStore::class)->set($store);
        $location = app(InventoryManager::class)->createLocation('Dashboard Depot');
        $product = Product::query()->create([
            'title' => 'Dashboard Product',
            'slug' => 'dashboard-product-'.str()->random(8),
            'status' => ProductStatus::Active,
        ]);
        $variant = $product->variants()->create([
            'title' => 'Default',
            'price' => '100.00',
            'status' => ProductStatus::Active,
        ]);
        Event::fake([InventoryAdjusted::class]);

        app(InventoryManager::class)->setAvailable($variant, $location, 12, 'cycle_count');

        Event::assertDispatched(
            InventoryAdjusted::class,
            fn (InventoryAdjusted $event): bool => $event->level->store_id === $store->id
                && $event->quantityBefore === 0
                && $event->quantityAfter === 12
                && $event->reason === 'cycle_count',
        );
    }

    private function createCustomer(string $email, string $createdAt): Customer
    {
        $customer = Customer::query()->create([
            'first_name' => 'Test',
            'last_name' => str($email)->before('@')->headline(),
            'email' => $email,
        ]);
        $timestamp = Carbon::parse($createdAt, 'Europe/Istanbul')->utc();
        $customer->forceFill(['created_at' => $timestamp, 'updated_at' => $timestamp])->saveQuietly();

        return $customer->refresh();
    }

    private function createOrder(
        string $number,
        string $total,
        string $placedAt,
        ?Customer $customer = null,
    ): Order {
        return Order::query()->create([
            'customer_id' => $customer?->id,
            'order_number' => $number,
            'payment_status' => OrderPaymentStatus::Paid,
            'currency' => 'TRY',
            'subtotal' => $total,
            'grand_total' => $total,
            'customer_email' => $customer?->email,
            'placed_at' => Carbon::parse($placedAt, 'Europe/Istanbul')->utc(),
        ]);
    }

    private function createFailedPayment(): Payment
    {
        $cart = Cart::query()->create([
            'token' => 'dashboard-cart-'.str()->ulid(),
            'currency' => 'TRY',
        ]);
        $checkout = CheckoutSession::query()->create([
            'cart_id' => $cart->id,
            'token' => 'dashboard-checkout-'.str()->ulid(),
            'currency' => 'TRY',
            'grand_total' => '25.00',
        ]);

        return Payment::query()->create([
            'checkout_id' => $checkout->id,
            'provider' => 'manual',
            'provider_payment_id' => 'dashboard-failed-'.str()->ulid(),
            'status' => GatewayPaymentStatus::Failed,
            'amount' => '25.00',
            'currency' => 'TRY',
            'failed_at' => now(),
        ]);
    }

    private function createRefund(Order $order, string $amount): void
    {
        $order->update(['payment_status' => OrderPaymentStatus::PartiallyRefunded]);
        $cart = Cart::query()->create([
            'token' => 'dashboard-refund-cart-'.str()->ulid(),
            'currency' => $order->currency,
        ]);
        $checkout = CheckoutSession::query()->create([
            'cart_id' => $cart->id,
            'token' => 'dashboard-refund-checkout-'.str()->ulid(),
            'currency' => $order->currency,
            'grand_total' => $order->grand_total,
        ]);
        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'checkout_id' => $checkout->id,
            'provider' => 'paytr',
            'provider_payment_id' => 'dashboard-paid-'.str()->ulid(),
            'status' => GatewayPaymentStatus::PartiallyRefunded,
            'amount' => $order->grand_total,
            'currency' => $order->currency,
            'paid_at' => $order->placed_at,
        ]);

        $payment->transactions()->create([
            'type' => PaymentTransactionType::Refund,
            'status' => PaymentTransactionStatus::Succeeded,
            'amount' => $amount,
            'provider_transaction_id' => 'dashboard-refund-'.str()->ulid(),
        ]);
    }

    /** @return array{0: User, 1: Store} */
    private function makeStoreWithUser(string $name): array
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

        return [$user, $store];
    }
}
