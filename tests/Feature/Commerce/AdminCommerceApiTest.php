<?php

namespace Tests\Feature\Commerce;

use App\Core\Tenancy\CurrentStore;
use App\Core\Tenancy\Scopes\StoreScope;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Modules\Commerce\Enums\Checkout\CheckoutState;
use Modules\Commerce\Enums\Order\FulfillmentStatus;
use Modules\Commerce\Enums\Order\OrderStatus;
use Modules\Commerce\Enums\Order\PaymentStatus;
use Modules\Commerce\Enums\Payment\PaymentStatus as GatewayPaymentStatus;
use Modules\Commerce\Models\Cart\Cart;
use Modules\Commerce\Models\Checkout\CheckoutSession;
use Modules\Commerce\Models\Customer\Customer;
use Modules\Commerce\Models\Order\Order;
use Modules\Commerce\Models\Payment\Payment;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Enums\StoreUserRole;
use Modules\Store\Enums\StoreUserStatus;
use Modules\Store\Models\Store;
use Modules\Store\Models\StoreUser;
use Tests\TestCase;

class AdminCommerceApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Referer', 'https://app.rivaify.com');
    }

    public function test_merchant_can_list_store_orders_customers_and_real_dashboard_metrics(): void
    {
        [$user, $store] = $this->makeStoreWithUser('Admin Commerce Store');
        app(CurrentStore::class)->set($store);
        $customer = Customer::query()->create([
            'first_name' => 'Ahmet',
            'last_name' => 'Yilmaz',
            'email' => 'ahmet@example.com',
            'total_orders' => 1,
            'total_spent' => '1890.00',
            'last_order_at' => now(),
        ]);
        $order = Order::query()->create([
            'customer_id' => $customer->id,
            'order_number' => 'RV-1004',
            'status' => OrderStatus::Open,
            'payment_status' => PaymentStatus::Paid,
            'fulfillment_status' => FulfillmentStatus::Unfulfilled,
            'currency' => 'TRY',
            'subtotal' => '1841.00',
            'shipping_total' => '49.00',
            'grand_total' => '1890.00',
            'customer_email' => $customer->email,
            'placed_at' => now(),
        ]);
        app(CurrentStore::class)->clear();
        Sanctum::actingAs($user);

        $this->withSession(['current_store_id' => $store->id])
            ->getJson('/api/v1/orders?q=ahmet')
            ->assertOk()
            ->assertJsonPath('data.0.id', $order->ulid)
            ->assertJsonPath('data.0.number', 'RV-1004')
            ->assertJsonPath('data.0.payment_status', 'paid');

        $this->withSession(['current_store_id' => $store->id])
            ->getJson('/api/v1/customers?q=ahmet@example.com')
            ->assertOk()
            ->assertJsonPath('data.0.id', $customer->ulid)
            ->assertJsonPath('data.0.total_spent', '1890.00');

        $this->withSession(['current_store_id' => $store->id])
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath('data.sales', '1890.00')
            ->assertJsonPath('data.orders', 1)
            ->assertJsonPath('data.average_order', '1890.00')
            ->assertJsonPath('data.customers', 1)
            ->assertJsonPath('data.recent_orders.0.id', $order->ulid);
    }

    public function test_merchant_cannot_access_another_stores_order_through_admin_api(): void
    {
        [$userA, $storeA] = $this->makeStoreWithUser('Admin Store A');
        [, $storeB] = $this->makeStoreWithUser('Admin Store B');
        app(CurrentStore::class)->set($storeB);
        $foreignOrder = Order::query()->create([
            'order_number' => 'RV-1001',
            'currency' => 'TRY',
            'grand_total' => '100.00',
            'placed_at' => now(),
        ]);
        app(CurrentStore::class)->clear();
        Sanctum::actingAs($userA);

        $this->withSession(['current_store_id' => $storeA->id])
            ->getJson("/api/v1/orders/{$foreignOrder->ulid}")
            ->assertNotFound();
    }

    public function test_merchant_can_manage_store_scoped_discount_rules(): void
    {
        [$user, $store] = $this->makeStoreWithUser('Discount Admin Store');
        Sanctum::actingAs($user);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $created = $this->withSession(['current_store_id' => $store->id])
            ->postJson('/api/v1/discounts', [
                'name' => 'Welcome 10',
                'code' => 'welcome10',
                'type' => 'percentage',
                'value' => '10.00',
                'minimum_purchase' => '1000.00',
                'conditions' => [[
                    'type' => 'cart_total',
                    'operator' => '>=',
                    'value' => ['amount' => '1000.00'],
                ]],
            ])
            ->assertCreated()
            ->assertJsonPath('data.code', 'WELCOME10')
            ->assertJsonPath('data.conditions.0.type', 'cart_total');
        $discountId = $created->json('data.id');

        $this->withSession(['current_store_id' => $store->id])
            ->patchJson("/api/v1/discounts/{$discountId}", ['value' => '15.00'])
            ->assertOk()
            ->assertJsonPath('data.value', '15.00');

        $this->withSession(['current_store_id' => $store->id])
            ->getJson('/api/v1/discounts')
            ->assertOk()
            ->assertJsonPath('data.0.id', $discountId)
            ->assertJsonPath('data.0.value', '15.00');
    }

    public function test_merchant_can_issue_a_payment_refund_for_its_order(): void
    {
        [$user, $store] = $this->makeStoreWithUser('Refund Admin Store');
        app(CurrentStore::class)->set($store);
        $cart = Cart::query()->create(['token' => 'refund-cart-'.str()->ulid(), 'currency' => 'TRY']);
        $checkout = CheckoutSession::query()->create([
            'cart_id' => $cart->id,
            'token' => 'refund-checkout-'.str()->ulid(),
            'status' => CheckoutState::Completed,
            'currency' => 'TRY',
            'grand_total' => '100.00',
        ]);
        $order = Order::query()->create([
            'checkout_id' => $checkout->id,
            'order_number' => 'RV-1001',
            'currency' => 'TRY',
            'grand_total' => '100.00',
            'customer_email' => 'refund@example.com',
            'payment_status' => PaymentStatus::Paid,
            'placed_at' => now(),
        ]);
        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'checkout_id' => $checkout->id,
            'provider' => 'manual',
            'provider_payment_id' => 'refund-payment-'.str()->ulid(),
            'status' => GatewayPaymentStatus::Paid,
            'amount' => '100.00',
            'currency' => 'TRY',
            'paid_at' => now(),
        ]);
        app(CurrentStore::class)->clear();
        Sanctum::actingAs($user);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $this->withSession(['current_store_id' => $store->id])
            ->postJson("/api/v1/orders/{$order->ulid}/payments/{$payment->ulid}/refund", ['amount' => '50.00'])
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'partially_refunded')
            ->assertJsonPath('data.payments.0.status', 'partially_refunded');
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function makeStoreWithUser(string $name): array
    {
        $user = User::factory()->create();
        $merchant = Merchant::create(['owner_user_id' => $user->id]);
        $store = $merchant->stores()->create([
            'name' => $name,
            'slug' => str($name)->slug(),
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