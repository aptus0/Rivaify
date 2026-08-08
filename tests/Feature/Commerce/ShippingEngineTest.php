<?php

namespace Tests\Feature\Commerce;

use App\Core\Tenancy\CurrentStore;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Commerce\DTOs\Customer\CustomerAddressData;
use Modules\Commerce\DTOs\Customer\UpsertCustomerData;
use Modules\Commerce\Enums\Checkout\CheckoutState;
use Modules\Commerce\Enums\Customer\CustomerAddressType;
use Modules\Commerce\Enums\Catalog\ProductStatus;
use Modules\Commerce\Enums\Shipping\ShippingMethodType;
use Modules\Commerce\Models\Cart\Cart;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Models\Shipping\ShippingMethod;
use Modules\Commerce\Models\Shipping\ShippingZone;
use Modules\Commerce\Services\Cart\CartManager;
use Modules\Commerce\Services\Checkout\CheckoutManager;
use Modules\Commerce\Services\Customer\CustomerManager;
use Modules\Commerce\Services\Shipping\ShippingEngine;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Models\Store;
use Tests\TestCase;

class ShippingEngineTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_only_quotes_methods_matching_zone_and_order_thresholds(): void
    {
        $store = $this->makeStore('Shipping Quote Store');
        $this->setCurrentStore($store);
        $cart = $this->cartWithActiveVariant('500.00', 2);
        $address = $this->makeAddress('shipping@example.com', 'Bursa');
        $turkiye = ShippingZone::query()->create(['name' => 'Turkiye']);
        $turkiye->regions()->create(['country_code' => 'TR']);
        $bursa = ShippingZone::query()->create(['name' => 'Bursa']);
        $bursa->regions()->create(['country_code' => 'TR', 'province' => 'Bursa']);

        $standard = ShippingMethod::query()->create([
            'shipping_zone_id' => $turkiye->id,
            'name' => 'Standart Kargo',
            'price' => '49.00',
        ]);
        $freeShipping = ShippingMethod::query()->create([
            'shipping_zone_id' => $bursa->id,
            'name' => 'Ucretsiz Kargo',
            'type' => ShippingMethodType::FreeShipping,
            'minimum_order' => '1000.00',
        ]);
        ShippingMethod::query()->create([
            'shipping_zone_id' => $bursa->id,
            'name' => 'Yuksek Tutar Kargosu',
            'price' => '10.00',
            'minimum_order' => '1000.01',
        ]);

        $quotes = app(ShippingEngine::class)->quotes($cart, $address);

        $this->assertCount(2, $quotes);
    $this->assertSame([$standard->id, $freeShipping->id], $quotes->pluck('method.id')->all());
        $this->assertSame('49.00', $quotes->first()->amount->toDecimal());
        $this->assertSame('0.00', $quotes->last()->amount->toDecimal());
    }

    public function test_it_snapshots_selected_shipping_on_the_checkout_and_advances_state(): void
    {
        $store = $this->makeStore('Shipping Checkout Store');
        $this->setCurrentStore($store);
        $cart = $this->cartWithActiveVariant('500.00', 2);
        $address = $this->makeAddress('checkout-shipping@example.com', 'Bursa');
        $method = ShippingMethod::query()->create([
            'name' => 'Standart Kargo',
            'price' => '49.00',
        ]);
        $checkoutManager = app(CheckoutManager::class);
        $checkout = $checkoutManager->start($cart);
        $checkout = $checkoutManager->provideCustomerInformation(
            $checkout,
            new UpsertCustomerData(email: 'checkout-shipping@example.com'),
        );
        $checkout = $checkoutManager->setAddresses($checkout, $address);

        $checkout = $checkoutManager->selectShipping($checkout, $method);

        $this->assertSame(CheckoutState::Shipping, $checkout->status);
        $this->assertSame($method->id, $checkout->shipping_method_id);
        $this->assertSame('49.00', $checkout->shipping_total);
        $this->assertSame('1049.00', $checkout->grand_total);
    }

    private function cartWithActiveVariant(string $price, int $quantity): Cart
    {
        $product = Product::query()->create([
            'title' => 'Shipping Product '.str()->random(8),
            'slug' => 'shipping-product-'.str()->random(12),
            'status' => ProductStatus::Active,
        ]);
        $variant = $product->variants()->create([
            'title' => 'Default',
            'price' => $price,
            'status' => ProductStatus::Active,
        ]);
        $cartManager = app(CartManager::class);
        $cart = $cartManager->getOrCreate();
        $cartManager->addItem($cart, $variant, $quantity);

        return $cart->fresh();
    }

    private function makeAddress(string $email, string $province)
    {
        $customerManager = app(CustomerManager::class);
        $customer = $customerManager->findOrCreate(new UpsertCustomerData(email: $email));

        return $customerManager->createAddress($customer, new CustomerAddressData(
            type: CustomerAddressType::Shipping,
            firstName: 'Ahmet',
            lastName: 'Yilmaz',
            countryCode: 'TR',
            addressLine1: 'Ataturk Mahallesi',
            province: $province,
            district: 'Karacabey',
        ));
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