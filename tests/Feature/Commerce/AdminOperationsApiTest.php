<?php

namespace Tests\Feature\Commerce;

use App\Core\Tenancy\CurrentStore;
use App\Core\Tenancy\Scopes\StoreScope;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Laravel\Sanctum\Sanctum;
use Modules\Commerce\Enums\Catalog\ProductStatus;
use Modules\Commerce\Enums\Catalog\ProductType;
use Modules\Commerce\Enums\Order\PaymentStatus as OrderPaymentStatus;
use Modules\Commerce\Enums\Payment\PaymentStatus as GatewayPaymentStatus;
use Modules\Commerce\Models\Cart\Cart;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Models\Catalog\ProductVariant;
use Modules\Commerce\Models\Checkout\CheckoutSession;
use Modules\Commerce\Models\Customer\Customer;
use Modules\Commerce\Models\Marketing\MarketingCampaign;
use Modules\Commerce\Models\Order\Order;
use Modules\Commerce\Models\Payment\Payment;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Enums\StoreUserRole;
use Modules\Store\Enums\StoreUserStatus;
use Modules\Store\Models\Store;
use Modules\Store\Models\StoreUser;
use Tests\TestCase;

class AdminOperationsApiTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-08-08 12:00:00', 'UTC'));
        $this->withHeader('Referer', 'https://app.rivaify.com');
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        app(CurrentStore::class)->clear();

        parent::tearDown();
    }

    public function test_analytics_is_tenant_and_currency_scoped(): void
    {
        [$owner, $store] = $this->makeStoreWithUser('Analytics Local');
        [, $foreignStore] = $this->makeStoreWithUser('Analytics Foreign');

        $this->inStore($store, function (): void {
            $customer = Customer::query()->create([
                'first_name' => 'Yerel',
                'last_name' => 'Müşteri',
                'email' => 'analytics-local@example.com',
                'total_orders' => 2,
            ]);
            $paid = $this->order('RV-ANALYTICS-LOCAL', '125.00', OrderPaymentStatus::Paid, $customer);
            $paid->items()->create([
                'product_title' => 'Yerel Analiz Ürünü',
                'variant_title' => 'Default',
                'quantity' => 2,
                'unit_price' => '62.50',
                'line_total' => '125.00',
            ]);
            $this->order('RV-ANALYTICS-PENDING', '15.00', OrderPaymentStatus::Pending, $customer);

            $otherCurrency = $this->order(
                'RV-ANALYTICS-EUR',
                '777.00',
                OrderPaymentStatus::Paid,
                $customer,
                'EUR',
            );
            $otherCurrency->items()->create([
                'product_title' => 'Yanlış Para Birimi Ürünü',
                'variant_title' => 'Default',
                'quantity' => 1,
                'unit_price' => '777.00',
                'line_total' => '777.00',
            ]);
        });

        $this->inStore($foreignStore, function (): void {
            $customer = Customer::query()->create([
                'first_name' => 'Yabancı',
                'last_name' => 'Müşteri',
                'email' => 'analytics-foreign@example.com',
                'total_orders' => 5,
            ]);
            $order = $this->order('RV-ANALYTICS-FOREIGN', '999.00', OrderPaymentStatus::Paid, $customer);
            $order->items()->create([
                'product_title' => 'Yabancı Analiz Ürünü',
                'variant_title' => 'Default',
                'quantity' => 9,
                'unit_price' => '111.00',
                'line_total' => '999.00',
            ]);
        });

        Sanctum::actingAs($owner);
        $response = $this->withSession(['current_store_id' => $store->id])
            ->getJson('/api/v1/analytics?range=30d')
            ->assertOk()
            ->assertJsonPath('data.currency', 'TRY')
            ->assertJsonPath('data.period.from', '2026-07-09T21:00:00+00:00')
            ->assertJsonPath('data.metrics.net_sales', '125.00')
            ->assertJsonPath('data.metrics.orders', 1)
            ->assertJsonPath('data.metrics.average_order', '125.00')
            ->assertJsonPath('data.metrics.new_customers', 1)
            ->assertJsonPath('data.metrics.returning_customers', 1)
            ->assertJsonPath('data.top_products.0.title', 'Yerel Analiz Ürünü')
            ->assertJsonPath('data.top_products.0.revenue', '125.00');

        $payload = $response->getContent();
        $this->assertStringNotContainsString('Yabancı Analiz Ürünü', $payload);
        $this->assertStringNotContainsString('Yanlış Para Birimi Ürünü', $payload);
        $this->assertSame(2, collect($response->json('data.payment_breakdown'))->sum('total'));
    }

    public function test_manager_can_run_tenant_scoped_marketing_crud_with_consistent_dates(): void
    {
        [$manager, $store] = $this->makeStoreWithUser('Marketing Local', StoreUserRole::Manager);
        [, $foreignStore] = $this->makeStoreWithUser('Marketing Foreign');
        $foreignCampaign = $this->inStore(
            $foreignStore,
            fn (): MarketingCampaign => MarketingCampaign::query()->create([
                'name' => 'Yabancı Kampanya',
                'channel' => 'online_store',
                'objective' => 'sales',
                'status' => 'draft',
                'currency' => 'TRY',
            ]),
        );

        Sanctum::actingAs($manager);
        $created = $this->withSession(['current_store_id' => $store->id])
            ->postJson('/api/v1/marketing/campaigns', [
                'name' => 'Yaz Duyurusu',
                'channel' => 'online_store',
                'objective' => 'sales',
                'status' => 'scheduled',
                'budget' => '250.50',
                'starts_at' => '2026-08-10T09:00:00Z',
                'ends_at' => '2026-08-20T09:00:00Z',
                'content' => ['message' => 'Yaz kampanyası başladı.'],
            ])
            ->assertCreated()
            ->assertJsonPath('data.currency', 'TRY')
            ->assertJsonPath('data.status', 'scheduled');
        $campaignId = $created->json('data.id');

        $this->withSession(['current_store_id' => $store->id])
            ->getJson('/api/v1/marketing/campaigns')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $campaignId);

        $this->withSession(['current_store_id' => $store->id])
            ->patchJson("/api/v1/marketing/campaigns/{$campaignId}", [
                'status' => 'active',
                'content' => ['message' => 'Mağaza duyurusu yayında.'],
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'active')
            ->assertJsonPath('data.message', 'Mağaza duyurusu yayında.');

        $this->withSession(['current_store_id' => $store->id])
            ->patchJson("/api/v1/marketing/campaigns/{$campaignId}", [
                'starts_at' => '2026-08-21T09:00:00Z',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ends_at');

        $this->withSession(['current_store_id' => $store->id])
            ->patchJson("/api/v1/marketing/campaigns/{$foreignCampaign->ulid}", ['name' => 'Kaçak Güncelleme'])
            ->assertNotFound();
        $this->withSession(['current_store_id' => $store->id])
            ->deleteJson("/api/v1/marketing/campaigns/{$foreignCampaign->ulid}")
            ->assertNotFound();

        $this->withSession(['current_store_id' => $store->id])
            ->deleteJson("/api/v1/marketing/campaigns/{$campaignId}")
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->inStore($foreignStore, function () use ($foreignCampaign): void {
            $this->assertTrue(MarketingCampaign::query()->whereKey($foreignCampaign->id)->exists());
        });
    }

    public function test_global_search_and_notifications_do_not_cross_store_boundaries(): void
    {
        [$owner, $store] = $this->makeStoreWithUser('Workspace Local');
        [, $foreignStore] = $this->makeStoreWithUser('Workspace Foreign');

        [$product, $variant, $customer, $order, $payment] = $this->inStore($store, function (): array {
            [$product, $variant] = $this->productVariant('Needle Yerel Ürün', 'NEEDLE-LOCAL', '45.00');
            $customer = Customer::query()->create([
                'first_name' => 'Needle',
                'last_name' => 'Yerel',
                'email' => 'needle-local@example.com',
            ]);
            $order = $this->order('NEEDLE-ORDER-LOCAL', '45.00', OrderPaymentStatus::Paid, $customer);
            $payment = $this->failedPayment('needle-local-payment@example.com');

            return [$product, $variant, $customer, $order, $payment];
        });

        $this->inStore($foreignStore, function (): void {
            $this->productVariant('Needle Yabancı Ürün', 'NEEDLE-FOREIGN', '999.00');
            $customer = Customer::query()->create([
                'first_name' => 'Needle',
                'last_name' => 'Yabancı',
                'email' => 'needle-foreign@example.com',
            ]);
            $this->order('NEEDLE-ORDER-FOREIGN', '999.00', OrderPaymentStatus::Paid, $customer);
            $this->failedPayment('needle-foreign-payment@example.com');
        });

        Sanctum::actingAs($owner);
        $search = $this->withSession(['current_store_id' => $store->id])
            ->getJson('/api/v1/search?q=needle')
            ->assertOk()
            ->assertJsonFragment(['id' => $product->ulid, 'type' => 'product'])
            ->assertJsonFragment(['id' => $order->ulid, 'type' => 'order'])
            ->assertJsonFragment(['id' => $customer->ulid, 'type' => 'customer']);
        $this->assertStringNotContainsString('FOREIGN', strtoupper($search->getContent()));
        $this->assertStringNotContainsString('YABANCI', strtoupper($search->getContent()));

        $notifications = $this->withSession(['current_store_id' => $store->id])
            ->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonFragment(['id' => 'order-'.$order->ulid])
            ->assertJsonFragment(['id' => 'payment-'.$payment->ulid]);
        $this->assertStringNotContainsString('NEEDLE-ORDER-FOREIGN', $notifications->getContent());
        $this->assertStringNotContainsString('needle-foreign-payment@example.com', $notifications->getContent());

        $this->withSession(['current_store_id' => $store->id])
            ->getJson('/api/v1/search?q=%25%25')
            ->assertOk()
            ->assertJsonCount(0, 'data');
        $this->withSession(['current_store_id' => $store->id])
            ->getJson('/api/v1/search?q=%20%20')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('q');

        $this->assertSame('NEEDLE-LOCAL', $variant->sku);
    }

    public function test_manual_order_options_and_creation_are_tenant_scoped_and_atomic(): void
    {
        [$owner, $store] = $this->makeStoreWithUser('Manual Order Local');
        [, $foreignStore] = $this->makeStoreWithUser('Manual Order Foreign');
        [$product, $variant, $customer] = $this->inStore($store, function (): array {
            [$product, $variant] = $this->productVariant('Yerel Manuel Ürün', 'MANUAL-LOCAL', '45.50');
            $customer = Customer::query()->create([
                'first_name' => 'Manuel',
                'last_name' => 'Müşteri',
                'email' => 'manual-local@example.com',
                'phone' => '+905551112233',
            ]);

            return [$product, $variant, $customer];
        });
        [$foreignVariant, $foreignCustomer] = $this->inStore($foreignStore, function (): array {
            [, $variant] = $this->productVariant('Yabancı Manuel Ürün', 'MANUAL-FOREIGN', '999.00');
            $customer = Customer::query()->create([
                'first_name' => 'Yabancı',
                'last_name' => 'Müşteri',
                'email' => 'manual-foreign@example.com',
            ]);

            return [$variant, $customer];
        });

        Sanctum::actingAs($owner);
        $options = $this->withSession(['current_store_id' => $store->id])
            ->getJson('/api/v1/orders/create-options')
            ->assertOk()
            ->assertJsonFragment(['id' => $variant->ulid, 'sku' => 'MANUAL-LOCAL'])
            ->assertJsonFragment(['id' => $customer->ulid, 'email' => 'manual-local@example.com']);
        $this->assertStringNotContainsString('MANUAL-FOREIGN', $options->getContent());
        $this->assertStringNotContainsString('manual-foreign@example.com', $options->getContent());

        $created = $this->withSession(['current_store_id' => $store->id])
            ->postJson('/api/v1/orders', [
                'customer_id' => $customer->ulid,
                'items' => [[
                    'variant_id' => $variant->ulid,
                    'quantity' => 2,
                ]],
                'shipping_total' => '9.00',
                'notes' => 'Telefon siparişi',
            ])
            ->assertCreated()
            ->assertJsonPath('data.customer.id', $customer->ulid)
            ->assertJsonPath('data.currency', 'TRY')
            ->assertJsonPath('data.subtotal', '91.00')
            ->assertJsonPath('data.shipping_total', '9.00')
            ->assertJsonPath('data.grand_total', '100.00')
            ->assertJsonPath('data.items.0.product_title', $product->title)
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.timeline.0.type', 'manual_order_created');
        $orderId = $created->json('data.id');

        $this->withSession(['current_store_id' => $store->id])
            ->postJson('/api/v1/orders', [
                'items' => [['variant_id' => $foreignVariant->ulid, 'quantity' => 1]],
            ])
            ->assertUnprocessable()
            ->assertJsonPath('message', 'Bir veya daha fazla ürün varyantı bulunamadı.');

        $this->withSession(['current_store_id' => $store->id])
            ->postJson('/api/v1/orders', [
                'customer_id' => $foreignCustomer->ulid,
                'items' => [['variant_id' => $variant->ulid, 'quantity' => 1]],
            ])
            ->assertNotFound();

        $this->inStore($store, function () use ($orderId): void {
            $this->assertSame(1, Order::query()->count());
            $this->assertSame(1, Order::query()->where('ulid', $orderId)->firstOrFail()->events()->count());
        });
    }

    public function test_roles_separate_reporting_marketing_and_manual_order_access(): void
    {
        [$staff, $store] = $this->makeStoreWithUser('Role Boundaries', StoreUserRole::Staff);
        [, $variant] = $this->inStore(
            $store,
            fn (): array => $this->productVariant('Rol Ürünü', 'ROLE-SKU', '10.00'),
        );
        Sanctum::actingAs($staff);

        $this->withSession(['current_store_id' => $store->id])
            ->getJson('/api/v1/analytics')
            ->assertForbidden();
        $this->withSession(['current_store_id' => $store->id])
            ->getJson('/api/v1/marketing/campaigns')
            ->assertForbidden();
        $this->withSession(['current_store_id' => $store->id])
            ->postJson('/api/v1/marketing/campaigns', [
                'name' => 'Yetkisiz',
                'channel' => 'online_store',
                'objective' => 'sales',
            ])
            ->assertForbidden();
        $this->withSession(['current_store_id' => $store->id])
            ->getJson('/api/v1/search?q=rol')
            ->assertOk();
        $this->withSession(['current_store_id' => $store->id])
            ->getJson('/api/v1/notifications')
            ->assertOk();
        $this->withSession(['current_store_id' => $store->id])
            ->postJson('/api/v1/orders', [
                'items' => [['variant_id' => $variant->ulid, 'quantity' => 1]],
            ])
            ->assertCreated();

        StoreUser::withoutGlobalScope(StoreScope::class)
            ->where('store_id', $store->id)
            ->where('user_id', $staff->id)
            ->update(['role' => StoreUserRole::Support]);

        $this->withSession(['current_store_id' => $store->id])
            ->getJson('/api/v1/orders/create-options')
            ->assertOk();
        $this->withSession(['current_store_id' => $store->id])
            ->postJson('/api/v1/orders', [
                'items' => [['variant_id' => $variant->ulid, 'quantity' => 1]],
            ])
            ->assertForbidden();
    }

    public function test_role_resolution_uses_the_selected_store_for_multi_store_users(): void
    {
        [$user, $ownerStore] = $this->makeStoreWithUser('Multi Store Owner', StoreUserRole::Owner);
        $merchant = $ownerStore->merchant()->firstOrFail();
        $supportStore = $merchant->stores()->create([
            'name' => 'Multi Store Support',
            'slug' => 'multi-store-support-'.str()->lower(str()->random(8)),
        ]);
        StoreUser::withoutGlobalScope(StoreScope::class)->create([
            'store_id' => $supportStore->id,
            'user_id' => $user->id,
            'role' => StoreUserRole::Support,
            'status' => StoreUserStatus::Active,
            'joined_at' => now(),
        ]);
        Sanctum::actingAs($user);

        $this->withSession(['current_store_id' => $ownerStore->id])
            ->getJson('/api/v1/analytics')
            ->assertOk();
        $this->withSession(['current_store_id' => $ownerStore->id])
            ->getJson('/api/v1/marketing/campaigns')
            ->assertOk();

        $this->withSession(['current_store_id' => $supportStore->id])
            ->getJson('/api/v1/analytics')
            ->assertForbidden();
        $this->withSession(['current_store_id' => $supportStore->id])
            ->getJson('/api/v1/marketing/campaigns')
            ->assertForbidden();
        $this->withSession(['current_store_id' => $supportStore->id])
            ->postJson('/api/v1/orders', [
                'items' => [['variant_id' => (string) str()->ulid(), 'quantity' => 1]],
            ])
            ->assertForbidden();
    }

    private function order(
        string $number,
        string $total,
        OrderPaymentStatus $paymentStatus,
        ?Customer $customer = null,
        string $currency = 'TRY',
    ): Order {
        return Order::query()->create([
            'customer_id' => $customer?->id,
            'order_number' => $number,
            'payment_status' => $paymentStatus,
            'currency' => $currency,
            'subtotal' => $total,
            'grand_total' => $total,
            'customer_email' => $customer?->email,
            'placed_at' => now(),
        ]);
    }

    /** @return array{0: Product, 1: ProductVariant} */
    private function productVariant(string $title, string $sku, string $price): array
    {
        $product = Product::query()->create([
            'title' => $title,
            'slug' => str($title)->slug().'-'.str()->lower(str()->random(6)),
            'product_type' => ProductType::Physical,
            'status' => ProductStatus::Active,
        ]);
        $variant = $product->variants()->create([
            'title' => 'Default',
            'sku' => $sku,
            'price' => $price,
            'status' => ProductStatus::Active,
        ]);

        return [$product, $variant];
    }

    private function failedPayment(string $email): Payment
    {
        $cart = Cart::query()->create([
            'token' => 'workspace-cart-'.str()->ulid(),
            'currency' => 'TRY',
        ]);
        $checkout = CheckoutSession::query()->create([
            'cart_id' => $cart->id,
            'token' => 'workspace-checkout-'.str()->ulid(),
            'email' => $email,
            'currency' => 'TRY',
            'grand_total' => '25.00',
        ]);

        return Payment::query()->create([
            'checkout_id' => $checkout->id,
            'provider' => 'manual',
            'provider_payment_id' => 'workspace-payment-'.str()->ulid(),
            'status' => GatewayPaymentStatus::Failed,
            'amount' => '25.00',
            'currency' => 'TRY',
            'failure_message' => 'Test failure',
            'failed_at' => now(),
        ]);
    }

    /** @return array{0: User, 1: Store} */
    private function makeStoreWithUser(string $name, StoreUserRole $role = StoreUserRole::Owner): array
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
            'role' => $role,
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
}
