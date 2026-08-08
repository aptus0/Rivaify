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
use Modules\Commerce\Exceptions\Checkout\CheckoutNotActiveException;
use Modules\Commerce\Exceptions\Checkout\CrossStoreCheckoutException;
use Modules\Commerce\Exceptions\Checkout\InvalidCheckoutTransitionException;
use Modules\Commerce\Models\Cart\Cart;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Models\Catalog\ProductVariant;
use Modules\Commerce\Services\Cart\CartManager;
use Modules\Commerce\Services\Checkout\CheckoutManager;
use Modules\Commerce\Services\Customer\CustomerManager;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Models\Store;
use Tests\TestCase;

class CheckoutManagerTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_starts_a_tokenized_checkout_with_a_cart_total_snapshot(): void
    {
        $store = $this->makeStore('Checkout Start Store');
        $this->setCurrentStore($store);
        $cart = $this->cartWithActiveVariant('4499.95', 2);

        $checkout = app(CheckoutManager::class)->start($cart);

        $this->assertSame(CheckoutState::Initiated, $checkout->status);
        $this->assertNotEmpty($checkout->token);
        $this->assertSame('8999.90', $checkout->subtotal);
        $this->assertSame('8999.90', $checkout->grand_total);
        $this->assertSame($checkout->id, app(CheckoutManager::class)->findByToken($checkout->token)?->id);
    }

    public function test_it_captures_customer_information_and_moves_to_the_next_step(): void
    {
        $store = $this->makeStore('Checkout Customer Store');
        $this->setCurrentStore($store);
        $checkout = app(CheckoutManager::class)->start($this->cartWithActiveVariant('99.00', 1));

        $checkout = app(CheckoutManager::class)->provideCustomerInformation(
            $checkout,
            new UpsertCustomerData(
                email: 'ahmet@example.com',
                firstName: 'Ahmet',
                lastName: 'Yilmaz',
                phone: '+905551112233',
            ),
        );

        $this->assertSame(CheckoutState::CustomerInformation, $checkout->status);
        $this->assertSame('ahmet@example.com', $checkout->email);
        $this->assertSame('+905551112233', $checkout->phone);
        $this->assertNotNull($checkout->customer_id);
        $this->assertSame($checkout->customer_id, $checkout->cart->customer_id);
    }

    public function test_it_accepts_addresses_owned_by_the_checkout_customer_and_moves_to_address_step(): void
    {
        $store = $this->makeStore('Checkout Address Store');
        $this->setCurrentStore($store);
        $checkoutManager = app(CheckoutManager::class);
        $customerManager = app(CustomerManager::class);
        $checkout = $checkoutManager->start($this->cartWithActiveVariant('99.00', 1));
        $checkout = $checkoutManager->provideCustomerInformation(
            $checkout,
            new UpsertCustomerData(email: 'address@example.com'),
        );
        $address = $customerManager->createAddress(
            $checkout->customer,
            $this->shippingAddressData('Ataturk Mahallesi'),
        );

        $checkout = $checkoutManager->setAddresses($checkout, $address);

        $this->assertSame(CheckoutState::Address, $checkout->status);
        $this->assertSame($address->id, $checkout->shipping_address_id);
        $this->assertSame($address->id, $checkout->billing_address_id);
    }

    public function test_it_rejects_an_address_from_a_different_customer(): void
    {
        $store = $this->makeStore('Checkout Ownership Store');
        $this->setCurrentStore($store);
        $checkoutManager = app(CheckoutManager::class);
        $customerManager = app(CustomerManager::class);
        $checkout = $checkoutManager->start($this->cartWithActiveVariant('99.00', 1));
        $checkout = $checkoutManager->provideCustomerInformation(
            $checkout,
            new UpsertCustomerData(email: 'first@example.com'),
        );
        $otherCustomer = $customerManager->findOrCreate(new UpsertCustomerData(email: 'other@example.com'));
        $otherAddress = $customerManager->createAddress($otherCustomer, $this->shippingAddressData('Diger Adres'));

        $this->expectException(CrossStoreCheckoutException::class);

        $checkoutManager->setAddresses($checkout, $otherAddress);
    }

    public function test_it_rejects_out_of_order_checkout_transitions(): void
    {
        $store = $this->makeStore('Checkout State Store');
        $this->setCurrentStore($store);
        $checkout = app(CheckoutManager::class)->start($this->cartWithActiveVariant('99.00', 1));
        $customer = app(CustomerManager::class)->findOrCreate(new UpsertCustomerData(email: 'state@example.com'));
        $address = app(CustomerManager::class)->createAddress($customer, $this->shippingAddressData('Durum Adresi'));

        $this->expectException(InvalidCheckoutTransitionException::class);

        app(CheckoutManager::class)->setAddresses($checkout, $address);
    }

    public function test_expired_checkout_cannot_accept_customer_information(): void
    {
        $store = $this->makeStore('Checkout Expiry Store');
        $this->setCurrentStore($store);
        $checkout = app(CheckoutManager::class)->start($this->cartWithActiveVariant('99.00', 1));
        $checkout->update(['expires_at' => now()->subMinute()]);

        $this->expectException(CheckoutNotActiveException::class);

        app(CheckoutManager::class)->provideCustomerInformation(
            $checkout,
            new UpsertCustomerData(email: 'expired@example.com'),
        );
    }

    private function cartWithActiveVariant(string $price, int $quantity): Cart
    {
        $product = Product::query()->create([
            'title' => 'Checkout Product '.str()->random(8),
            'slug' => 'checkout-product-'.str()->random(12),
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

    private function shippingAddressData(string $addressLine): CustomerAddressData
    {
        return new CustomerAddressData(
            type: CustomerAddressType::Shipping,
            firstName: 'Ahmet',
            lastName: 'Yilmaz',
            countryCode: 'TR',
            addressLine1: $addressLine,
            province: 'Bursa',
            district: 'Karacabey',
        );
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