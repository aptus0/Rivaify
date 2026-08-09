<?php

namespace Tests\Feature\Commerce;

use App\Core\Tenancy\CurrentStore;
use App\Core\Tenancy\Scopes\StoreScope;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Modules\Commerce\Enums\Analytics\StorefrontEventType;
use Modules\Commerce\Enums\Catalog\ProductStatus;
use Modules\Commerce\Enums\Checkout\CheckoutState;
use Modules\Commerce\Enums\Order\PaymentStatus as OrderPaymentStatus;
use Modules\Commerce\Enums\Payment\PaymentStatus as GatewayPaymentStatus;
use Modules\Commerce\Enums\Payment\PaymentTransactionStatus;
use Modules\Commerce\Enums\Payment\PaymentTransactionType;
use Modules\Commerce\Models\Analytics\StorefrontEvent;
use Modules\Commerce\Models\Cart\Cart;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Models\Checkout\CheckoutSession;
use Modules\Commerce\Models\Order\Order;
use Modules\Commerce\Models\Payment\Payment;
use Modules\Commerce\Models\Payment\PaymentTransaction;
use Modules\Commerce\Services\Analytics\StorefrontEventRecorder;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Enums\StoreStatus;
use Modules\Store\Enums\StoreUserRole;
use Modules\Store\Enums\StoreUserStatus;
use Modules\Store\Models\Store;
use Modules\Store\Models\StoreUser;
use Tests\TestCase;

class StorefrontAnalyticsTrackingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-08 12:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        app(CurrentStore::class)->clear();

        parent::tearDown();
    }

    public function test_client_events_are_pii_minimized_tenant_scoped_and_cannot_forge_purchases(): void
    {
        [, $store] = $this->makeStoreWithUser('Tracking Local');
        [, $foreignStore] = $this->makeStoreWithUser('Tracking Foreign');
        $localProduct = $this->inStore($store, fn (): Product => $this->product('Yerel İzleme Ürünü'));
        $foreignProduct = $this->inStore($foreignStore, fn (): Product => $this->product('Yabancı İzleme Ürünü'));
        $sessionId = 'anonymous-session-1234567890';

        $this->postJson($this->storefrontUrl($store, '/api/storefront/v1/events'), [
            'event_type' => 'purchase',
            'session_id' => $sessionId,
        ])
            ->assertUnprocessable()
            ->assertExactJson(['message' => 'purchase_event_server_only']);

        $this->postJson($this->storefrontUrl($store, '/api/storefront/v1/events'), [
            'event_type' => 'product_view',
            'session_id' => $sessionId,
            'product_id' => $foreignProduct->ulid,
        ])->assertNotFound();

        $this->postJson($this->storefrontUrl($store, '/api/storefront/v1/events'), [
            'event_type' => 'page_view',
            'session_id' => $sessionId,
            'path' => '/checkouts/customer@example.com/confirmation?email=customer@example.com',
            'utm_source' => 'Newsletter',
            'utm_medium' => 'Email',
            'utm_campaign' => 'customer@example.com',
            'referrer_host' => 'www.google.com',
        ])
            ->assertStatus(202)
            ->assertJsonPath('data.accepted', true);

        $this->postJson($this->storefrontUrl($store, '/api/storefront/v1/events'), [
            'event_type' => 'product_view',
            'session_id' => $sessionId,
            'product_id' => $localProduct->ulid,
            'path' => '/products/yerel-izleme-urunu',
        ])->assertStatus(202);

        $event = StorefrontEvent::withoutGlobalScope(StoreScope::class)
            ->where('store_id', $store->id)
            ->where('event_type', StorefrontEventType::PageView->value)
            ->sole();
        $this->assertNotSame($sessionId, $event->session_hash);
        $this->assertSame(64, strlen($event->session_hash));
        $this->assertSame('/checkouts/:token/confirmation', $event->page_path);
        $this->assertSame('newsletter', $event->source);
        $this->assertSame('google.com', $event->referrer_host);
        $this->assertNull($event->utm_campaign);
        $stored = json_encode($event->getAttributes(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('customer@example.com', $stored);
        $this->assertStringNotContainsString($sessionId, $stored);
        $this->assertSame(0, StorefrontEvent::withoutGlobalScope(StoreScope::class)
            ->where('event_type', StorefrontEventType::Purchase->value)->count());
        $this->assertSame(0, StorefrontEvent::withoutGlobalScope(StoreScope::class)
            ->where('store_id', $foreignStore->id)->count());

        $columns = Schema::getColumnListing('storefront_events');
        foreach (['session_id', 'ip', 'ip_address', 'user_agent', 'email', 'referrer_url'] as $piiColumn) {
            $this->assertNotContains($piiColumn, $columns);
        }
    }

    public function test_admin_traffic_sources_and_funnel_are_aggregated_per_store(): void
    {
        [$owner, $store] = $this->makeStoreWithUser('Analytics Events Local');
        [, $foreignStore] = $this->makeStoreWithUser('Analytics Events Foreign');

        $this->inStore($store, function (): void {
            $this->event(StorefrontEventType::PageView, 'session-1', 'google');
            $this->event(StorefrontEventType::ProductView, 'session-1', 'google');
            $this->event(StorefrontEventType::AddToCart, 'session-1', 'google');
            $this->event(StorefrontEventType::CheckoutStarted, 'session-1', 'google');
            $this->event(StorefrontEventType::Purchase, 'session-1', 'google', orderId: null);
            $this->event(StorefrontEventType::PageView, 'session-2', 'direct');
            $this->event(StorefrontEventType::ProductView, 'session-2', 'direct');
            $this->event(StorefrontEventType::PageView, 'session-3', 'google');
        });
        $this->inStore($foreignStore, function (): void {
            $this->event(StorefrontEventType::PageView, 'foreign-session', 'foreign-source');
            $this->event(StorefrontEventType::Purchase, 'foreign-session', 'foreign-source', orderId: null);
        });

        Sanctum::actingAs($owner);
        $response = $this->withHeader('Referer', 'https://app.rivaify.com')
            ->withSession(['current_store_id' => $store->id])
            ->getJson('/api/v1/analytics?range=30d')
            ->assertOk()
            ->assertJsonPath('data.traffic.available', true)
            ->assertJsonPath('data.traffic.sessions', 3)
            ->assertJsonPath('data.traffic.total_events', 8)
            ->assertJsonPath('data.traffic.sources.0.source', 'google')
            ->assertJsonPath('data.traffic.sources.0.sessions', 2)
            ->assertJsonPath('data.traffic.sources.0.share', 66.7)
            ->assertJsonPath('data.traffic.sources.1.source', 'direct')
            ->assertJsonPath('data.traffic.funnel.0.key', 'page_view')
            ->assertJsonPath('data.traffic.funnel.0.sessions', 3)
            ->assertJsonPath('data.traffic.funnel.1.key', 'product_view')
            ->assertJsonPath('data.traffic.funnel.1.sessions', 2)
            ->assertJsonPath('data.traffic.funnel.2.sessions', 1)
            ->assertJsonPath('data.traffic.funnel.3.sessions', 1)
            ->assertJsonPath('data.traffic.funnel.4.key', 'purchase')
            ->assertJsonPath('data.traffic.funnel.4.sessions', 1)
            ->assertJsonPath('data.traffic.funnel.4.conversion_rate', 33.3);

        $this->assertStringNotContainsString('foreign-source', $response->getContent());
    }

    public function test_server_purchase_recorder_requires_paid_order_and_is_idempotent(): void
    {
        [, $store] = $this->makeStoreWithUser('Trusted Purchase Store');

        $this->inStore($store, function (): void {
            [$checkout, $order, $payment] = $this->paymentGraph('TRUSTED', true);
            StorefrontEvent::query()->create([
                'event_type' => StorefrontEventType::CheckoutStarted,
                'session_hash' => str_repeat('a', 64),
                'checkout_id' => $checkout->id,
                'page_path' => '/checkouts/:token',
                'source' => 'instagram',
                'utm_source' => 'instagram',
                'utm_medium' => 'social',
                'occurred_at' => now()->subMinute(),
            ]);

            $recorder = app(StorefrontEventRecorder::class);
            $recorder->recordPurchase($payment);
            $recorder->recordPurchase($payment);

            $purchase = StorefrontEvent::query()
                ->where('event_type', StorefrontEventType::Purchase->value)
                ->sole();
            $this->assertSame($order->id, $purchase->order_id);
            $this->assertSame($checkout->id, $purchase->checkout_id);
            $this->assertSame(str_repeat('a', 64), $purchase->session_hash);
            $this->assertSame('instagram', $purchase->source);

            [, , $pendingPayment] = $this->paymentGraph('PENDING', false);
            $recorder->recordPurchase($pendingPayment);
            $this->assertSame(1, StorefrontEvent::query()
                ->where('event_type', StorefrontEventType::Purchase->value)
                ->count());
        });
    }

    public function test_storefront_event_endpoint_has_a_dedicated_rate_limit(): void
    {
        [, $store] = $this->makeStoreWithUser('Tracking Rate Limit');
        $url = $this->storefrontUrl($store, '/api/storefront/v1/events');

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->postJson($url, [
                'event_type' => 'page_view',
                'session_id' => 'rate-limit-session-123456',
                'path' => '/',
            ])->assertStatus(202);
        }

        $this->postJson($url, [
            'event_type' => 'page_view',
            'session_id' => 'rate-limit-session-123456',
            'path' => '/',
        ])->assertTooManyRequests();
    }

    public function test_analytics_net_sales_subtracts_only_scoped_successful_refunds(): void
    {
        [$owner, $store] = $this->makeStoreWithUser('Refund Analytics Local');
        [, $foreignStore] = $this->makeStoreWithUser('Refund Analytics Foreign');

        $this->inStore($store, function (): void {
            [, $order, $payment] = $this->paymentGraph('NET-TRY', true);
            $this->refund($payment, '30.00');
            $order->update(['payment_status' => OrderPaymentStatus::PartiallyRefunded]);

            [, $refundedOrder, $refundedPayment] = $this->paymentGraph('FULL-REFUND', true, '20.00');
            $this->refund($refundedPayment, '20.00');
            $refundedOrder->update(['payment_status' => OrderPaymentStatus::Refunded]);

            [, $oldRefundOrder, $oldRefundPayment] = $this->paymentGraph('OLD-REFUND', true, '40.00');
            $oldRefund = $this->refund($oldRefundPayment, '10.00');
            $oldRefund->forceFill(['created_at' => now()->subDays(40)])->saveQuietly();
            $oldRefundOrder->update(['payment_status' => OrderPaymentStatus::PartiallyRefunded]);

            [, $euroOrder, $euroPayment] = $this->paymentGraph('NET-EUR', true, '50.00', 'EUR');
            $this->refund($euroPayment, '20.00');
            $euroOrder->update(['payment_status' => OrderPaymentStatus::PartiallyRefunded]);
        });
        $this->inStore($foreignStore, function (): void {
            [, $order, $payment] = $this->paymentGraph('FOREIGN-REFUND', true, '200.00');
            $this->refund($payment, '100.00');
            $order->update(['payment_status' => OrderPaymentStatus::PartiallyRefunded]);
        });

        Sanctum::actingAs($owner);
        $this->withHeader('Referer', 'https://app.rivaify.com')
            ->withSession(['current_store_id' => $store->id])
            ->getJson('/api/v1/analytics?range=30d')
            ->assertOk()
            ->assertJsonPath('data.metrics.net_sales', '110.00')
            ->assertJsonPath('data.metrics.refunds', '50.00')
            ->assertJsonPath('data.metrics.orders', 3)
            ->assertJsonPath('data.metrics.average_order', '36.67')
            ->assertJsonPath('data.series.0.sales', '110.00')
            ->assertJsonPath('data.series.0.gross_sales', '160.00')
            ->assertJsonPath('data.series.0.refunds', '50.00')
            ->assertJsonPath('data.series.0.orders', 3)
            ->assertJsonPath('data.top_products_basis', 'gross_order_item_revenue_excludes_refunds');
    }

    private function event(
        StorefrontEventType $type,
        string $session,
        string $source,
        ?int $orderId = null,
    ): StorefrontEvent {
        return StorefrontEvent::query()->create([
            'event_type' => $type,
            'session_hash' => hash('sha256', $session),
            'order_id' => $orderId,
            'page_path' => '/',
            'source' => $source,
            'occurred_at' => now(),
        ]);
    }

    /** @return array{0: CheckoutSession, 1: Order, 2: Payment} */
    private function paymentGraph(
        string $suffix,
        bool $paid,
        string $amount = '100.00',
        string $currency = 'TRY',
    ): array {
        $cart = Cart::query()->create([
            'token' => 'tracking-cart-'.str()->ulid(),
            'currency' => $currency,
            'grand_total' => $amount,
        ]);
        $checkout = CheckoutSession::query()->create([
            'cart_id' => $cart->id,
            'token' => 'tracking-checkout-'.str()->ulid(),
            'status' => $paid ? CheckoutState::Completed : CheckoutState::Payment,
            'currency' => $currency,
            'grand_total' => $amount,
        ]);
        $order = Order::query()->create([
            'checkout_id' => $checkout->id,
            'order_number' => 'RV-TRACKING-'.$suffix,
            'payment_status' => $paid ? OrderPaymentStatus::Paid : OrderPaymentStatus::Pending,
            'currency' => $currency,
            'grand_total' => $amount,
            'placed_at' => now(),
        ]);
        $payment = Payment::query()->create([
            'order_id' => $order->id,
            'checkout_id' => $checkout->id,
            'provider' => 'manual',
            'provider_payment_id' => 'tracking-payment-'.$suffix,
            'status' => $paid ? GatewayPaymentStatus::Paid : GatewayPaymentStatus::Pending,
            'amount' => $amount,
            'currency' => $currency,
            'paid_at' => $paid ? now() : null,
        ]);

        return [$checkout, $order, $payment];
    }

    private function refund(Payment $payment, string $amount): PaymentTransaction
    {
        return $payment->transactions()->create([
            'type' => PaymentTransactionType::Refund,
            'status' => PaymentTransactionStatus::Succeeded,
            'amount' => $amount,
            'provider_transaction_id' => 'refund-'.str()->ulid(),
        ]);
    }

    private function product(string $title): Product
    {
        return Product::query()->create([
            'title' => $title,
            'slug' => str($title)->slug().'-'.str()->lower(str()->random(6)),
            'status' => ProductStatus::Active,
        ]);
    }

    /** @return array{0: User, 1: Store} */
    private function makeStoreWithUser(string $name): array
    {
        $user = User::factory()->create();
        $merchant = Merchant::query()->create(['owner_user_id' => $user->id]);
        $store = $merchant->stores()->create([
            'name' => $name,
            'slug' => str($name)->slug().'-'.str()->lower(str()->random(8)),
            'status' => StoreStatus::Active,
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

    private function inStore(Store $store, callable $callback): mixed
    {
        $currentStore = app(CurrentStore::class);
        $currentStore->set($store);

        try {
            return $callback();
        } finally {
            $currentStore->clear();
        }
    }

    private function storefrontUrl(Store $store, string $path): string
    {
        return "http://{$store->slug}.rivaify.com{$path}";
    }
}
