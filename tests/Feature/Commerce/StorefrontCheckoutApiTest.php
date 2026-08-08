<?php

namespace Tests\Feature\Commerce;

use App\Core\Tenancy\CurrentStore;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Commerce\Enums\Catalog\ProductStatus;
use Modules\Commerce\Enums\Discount\DiscountType;
use Modules\Commerce\Models\Cart\Cart;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Models\Discount\Discount;
use Modules\Commerce\Models\Inventory\InventoryLevel;
use Modules\Commerce\Models\Order\Order;
use Modules\Commerce\Models\Shipping\ShippingMethod;
use Modules\Commerce\Models\Tax\TaxRate;
use Modules\Commerce\Services\Inventory\InventoryManager;
use Modules\Merchant\Models\Merchant;
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
        $baseUrl = "http://{$store->slug}.rivaify.test/api/storefront/v1";

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
        ]);
    }
}