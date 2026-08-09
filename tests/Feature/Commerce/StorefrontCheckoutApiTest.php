<?php

namespace Tests\Feature\Commerce;

use App\Core\Tenancy\CurrentStore;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Modules\Commerce\Enums\Catalog\ProductStatus;
use Modules\Commerce\Enums\Discount\DiscountType;
use Modules\Commerce\Models\Cart\Cart;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Models\Checkout\CheckoutSession;
use Modules\Commerce\Models\Discount\Discount;
use Modules\Commerce\Models\Inventory\InventoryReservation;
use Modules\Commerce\Models\Order\Order;
use Modules\Commerce\Models\Shipping\ShippingMethod;
use Modules\Commerce\Models\Tax\TaxRate;
use Modules\Commerce\Services\Inventory\InventoryManager;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Enums\StoreStatus;
use Modules\Store\Models\Store;
use Tests\TestCase;

class StorefrontCheckoutApiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_storefront_completes_a_discounted_checkout_and_commits_inventory(): void
    {
        $store = $this->makeStore('Yasemin Giyim');
        app(CurrentStore::class)->set($store);
        $product = Product::query()->create([
            'title' => 'Nike Air Max',
            'slug' => 'nike-air-max',
            'status' => ProductStatus::Active,
        ]);
        $variant = $product->variants()->create([
            'title' => 'Black / 42',
            'sku' => 'NIKE-BLK-42',
            'price' => '4499.00',
            'status' => ProductStatus::Active,
        ]);
        $inventory = app(InventoryManager::class);
        $location = $inventory->createLocation('Karacabey Depo');
        $level = $inventory->setAvailable($variant, $location, 5);
        ShippingMethod::query()->create(['name' => 'Standart Kargo', 'price' => '49.00']);
        TaxRate::query()->create(['name' => 'KDV %20', 'country_code' => 'TR', 'rate' => '20.00']);
        Discount::query()->create([
            'name' => 'Welcome 10',
            'code' => 'WELCOME10',
            'type' => DiscountType::Percentage,
            'value' => '10.00',
        ]);
        app(CurrentStore::class)->clear();
        $baseUrl = "http://{$store->slug}.rivaify.com/api/storefront/v1";

        $this->postJson("{$baseUrl}/cart/items", [
            'variant_id' => $variant->ulid,
            'quantity' => 1,
            'price' => '1.00',
        ])
            ->assertOk()
            ->assertJsonPath('data.grand_total', '4499.00');
        $cartToken = Cart::query()->sole()->token;

        $checkoutResponse = $this->guestRequest($cartToken)
            ->postJson("{$baseUrl}/checkout")
            ->assertCreated()
            ->assertJsonPath('data.status', 'initiated');
        $checkoutToken = $checkoutResponse->json('data.token');

        $this->guestRequest($cartToken)
            ->patchJson("{$baseUrl}/checkouts/{$checkoutToken}/customer", [
                'email' => 'ahmet@example.com',
                'first_name' => 'Ahmet',
                'last_name' => 'Yilmaz',
                'phone' => '+905551112233',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'customer_information');

        $this->guestRequest($cartToken)
            ->patchJson("{$baseUrl}/checkouts/{$checkoutToken}/address", [
                'shipping' => [
                    'first_name' => 'Ahmet',
                    'last_name' => 'Yilmaz',
                    'country_code' => 'TR',
                    'province' => 'Bursa',
                    'district' => 'Karacabey',
                    'address_line_1' => 'Ataturk Mahallesi',
                    'postal_code' => '16700',
                ],
                'billing_same_as_shipping' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'address');

        $shippingMethodId = $this->guestRequest($cartToken)
            ->getJson("{$baseUrl}/checkouts/{$checkoutToken}/shipping-methods")
            ->assertOk()
            ->json('data.0.id');

        $this->guestRequest($cartToken)
            ->postJson("{$baseUrl}/checkouts/{$checkoutToken}/shipping", ['shipping_method_id' => $shippingMethodId])
            ->assertOk()
            ->assertJsonPath('data.status', 'shipping')
            ->assertJsonPath('data.shipping_total', '49.00');

        $this->guestRequest($cartToken)
            ->postJson("{$baseUrl}/checkouts/{$checkoutToken}/discount", ['code' => 'WELCOME10'])
            ->assertOk()
            ->assertJsonPath('data.discount_total', '449.90')
            ->assertJsonPath('data.tax_total', '809.82')
            ->assertJsonPath('data.grand_total', '4907.92');

        $this->guestRequest($cartToken)
            ->withHeader('Idempotency-Key', 'storefront-blocked-manual-payment-key')
            ->postJson("{$baseUrl}/checkouts/{$checkoutToken}/payment", [
                'provider' => 'manual',
                'payment_method_type' => 'card',
            ])
            ->assertUnprocessable();

        config()->set('commerce.payments.allow_manual_storefront', true);
        config()->set('commerce.payments.storefront_providers', ['manual']);
        $payment = $this->guestRequest($cartToken)
            ->withHeader('Idempotency-Key', 'storefront-checkout-payment-key')
            ->postJson("{$baseUrl}/checkouts/{$checkoutToken}/payment", [
                'provider' => 'manual',
                'payment_method_type' => 'card',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid')
            ->assertJsonPath('data.checkout.status', 'completed');

        $this->assertNotNull($payment->json('data.order_id'));
        $this->assertSame(1, Order::query()->count());
        $this->assertSame('RV-1001', Order::query()->sole()->order_number);
        $this->assertSame(4, $level->fresh()->available_quantity);
        $this->assertSame(0, $level->fresh()->reserved_quantity);
    }

    public function test_checkout_customer_requires_paytr_contact_fields_and_enforces_limits(): void
    {
        $store = $this->makeStore('PayTR Contact Store');
        app(CurrentStore::class)->set($store);
        $cart = Cart::query()->create([
            'token' => 'contact-cart-'.str()->ulid(),
            'currency' => 'TRY',
        ]);
        $checkout = CheckoutSession::query()->create([
            'cart_id' => $cart->id,
            'token' => 'contact-checkout-'.str()->ulid(),
            'currency' => 'TRY',
        ]);
        app(CurrentStore::class)->clear();
        $url = "http://{$store->slug}.rivaify.com/api/storefront/v1/checkouts/{$checkout->token}/customer";

        $this->guestRequest($cart->token)
            ->patchJson($url, ['accepts_marketing' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'first_name', 'last_name', 'phone']);

        $longEmail = str_repeat('a', 63).'@'.str_repeat('b', 36).'.com';
        $this->guestRequest($cart->token)
            ->patchJson($url, [
                'email' => $longEmail,
                'first_name' => str_repeat('A', 31),
                'last_name' => str_repeat('B', 31),
                'phone' => str_repeat('5', 21),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'first_name', 'last_name', 'phone']);

        $this->guestRequest($cart->token)
            ->patchJson($url, [
                'email' => 'buyer@example.com',
                'first_name' => str_repeat('A', 30),
                'last_name' => str_repeat('B', 30),
                'phone' => str_repeat('5', 20),
            ])
            ->assertOk()
            ->assertJsonPath('data.email', 'buyer@example.com');
    }

    public function test_paytr_payment_reserves_inventory_beyond_the_iframe_timeout_window(): void
    {
        $this->travelTo(now()->startOfSecond());
        config()->set('commerce.payments.storefront_providers', ['paytr']);
        config()->set('commerce.payments.paytr', [
            'merchant_id' => 'merchant-id',
            'merchant_key' => 'merchant-key',
            'merchant_salt' => 'merchant-salt',
            'test_mode' => true,
            'debug' => false,
            'timeout' => 30,
            'max_installment' => 12,
            'no_installment' => false,
            'token_url' => 'https://paytr.test/get-token',
            'iframe_url' => 'https://paytr.test/secure',
            'refund_url' => 'https://paytr.test/refund',
        ]);
        Http::preventStrayRequests();
        Http::fake([
            'https://paytr.test/get-token' => Http::response([
                'status' => 'success',
                'token' => 'reservation-timeout-token',
            ]),
        ]);

        $store = $this->makeStore('PayTR Reservation Store');
        app(CurrentStore::class)->set($store);
        $product = Product::query()->create([
            'title' => 'PayTR Reservation Product',
            'slug' => 'paytr-reservation-product',
            'status' => ProductStatus::Active,
        ]);
        $variant = $product->variants()->create([
            'title' => 'Default',
            'sku' => 'PAYTR-RESERVE-1',
            'price' => '100.00',
            'status' => ProductStatus::Active,
        ]);
        $inventory = app(InventoryManager::class);
        $location = $inventory->createLocation('PayTR Reservation Warehouse');
        $inventory->setAvailable($variant, $location, 2);
        ShippingMethod::query()->create(['name' => 'PayTR Shipping', 'price' => '0.00']);
        app(CurrentStore::class)->clear();
        $baseUrl = "http://{$store->slug}.rivaify.com/api/storefront/v1";

        $this->postJson("{$baseUrl}/cart/items", [
            'variant_id' => $variant->ulid,
            'quantity' => 1,
        ])->assertOk();
        $cartToken = Cart::query()->sole()->token;
        $checkoutToken = $this->guestRequest($cartToken)
            ->postJson("{$baseUrl}/checkout")
            ->assertCreated()
            ->json('data.token');
        $this->guestRequest($cartToken)
            ->patchJson("{$baseUrl}/checkouts/{$checkoutToken}/customer", [
                'email' => 'paytr@example.com',
                'first_name' => 'PayTR',
                'last_name' => 'Customer',
                'phone' => '+905551112233',
            ])
            ->assertOk();
        $this->guestRequest($cartToken)
            ->patchJson("{$baseUrl}/checkouts/{$checkoutToken}/address", [
                'shipping' => [
                    'first_name' => 'PayTR',
                    'last_name' => 'Customer',
                    'country_code' => 'TR',
                    'province' => 'Bursa',
                    'district' => 'Karacabey',
                    'address_line_1' => 'Ataturk Mahallesi',
                    'postal_code' => '16700',
                ],
                'billing_same_as_shipping' => true,
            ])
            ->assertOk();
        $shippingMethodId = $this->guestRequest($cartToken)
            ->getJson("{$baseUrl}/checkouts/{$checkoutToken}/shipping-methods")
            ->assertOk()
            ->json('data.0.id');
        $this->guestRequest($cartToken)
            ->postJson("{$baseUrl}/checkouts/{$checkoutToken}/shipping", [
                'shipping_method_id' => $shippingMethodId,
            ])
            ->assertOk();

        $this->guestRequest($cartToken)
            ->withHeader('Idempotency-Key', 'paytr-reservation-timeout-key')
            ->postJson("{$baseUrl}/checkouts/{$checkoutToken}/payment", [
                'provider' => 'paytr',
                'payment_method_type' => 'card',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.gateway.provider', 'paytr')
            ->assertJsonPath('data.gateway.iframe_url', 'https://paytr.test/secure/reservation-timeout-token');

        $reservation = InventoryReservation::query()->sole();
        $this->assertTrue($reservation->expires_at->equalTo(now()->addMinutes(35)));
        $this->assertTrue($reservation->expires_at->greaterThan(now()->addMinutes(30)));
        Http::assertSentCount(1);
    }

    private function guestRequest(string $cartToken): static
    {
        return $this->withCredentials()->withUnencryptedCookie('rivaify_cart', $cartToken);
    }

    private function makeStore(string $name): Store
    {
        $user = User::factory()->create();
        $merchant = Merchant::create(['owner_user_id' => $user->id]);

        return $merchant->stores()->create([
            'name' => $name,
            'slug' => str($name)->slug(),
            'status' => StoreStatus::Active,
        ]);
    }
}
