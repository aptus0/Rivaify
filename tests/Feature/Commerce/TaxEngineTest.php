<?php

namespace Tests\Feature\Commerce;

use App\Core\Tenancy\CurrentStore;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Commerce\DTOs\Customer\CustomerAddressData;
use Modules\Commerce\DTOs\Customer\UpsertCustomerData;
use Modules\Commerce\Enums\Customer\CustomerAddressType;
use Modules\Commerce\Enums\Catalog\ProductStatus;
use Modules\Commerce\Models\Cart\Cart;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Models\Tax\TaxRate;
use Modules\Commerce\Services\Cart\CartManager;
use Modules\Commerce\Services\Checkout\CheckoutManager;
use Modules\Commerce\Services\Customer\CustomerManager;
use Modules\Commerce\Services\Tax\TaxEngine;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Models\Store;
use Tests\TestCase;

class TaxEngineTest extends TestCase
{
    use DatabaseTransactions;

    public function test_exclusive_tax_is_added_to_cart_total(): void
    {
        $store = $this->makeStore('Tax Exclusive Store');
        $this->setCurrentStore($store);
        $cart = $this->cartWithVariant('500.00');
        $address = $this->makeAddress('exclusive-tax@example.com');
        TaxRate::query()->create([
            'name' => 'KDV %20',
            'country_code' => 'TR',
            'rate' => '20.00',
            'is_inclusive' => false,
        ]);

        $cart = app(TaxEngine::class)->apply($cart, $address);

        $this->assertSame('100.00', $cart->tax_total);
        $this->assertFalse($cart->tax_inclusive);
        $this->assertSame('600.00', $cart->grand_total);
    }

    public function test_inclusive_tax_is_snapshotted_without_being_added_twice_to_total(): void
    {
        $store = $this->makeStore('Tax Inclusive Store');
        $this->setCurrentStore($store);
        $cart = $this->cartWithVariant('120.00');
        $address = $this->makeAddress('inclusive-tax@example.com');
        TaxRate::query()->create([
            'name' => 'KDV Dahil %20',
            'country_code' => 'TR',
            'rate' => '20.00',
            'is_inclusive' => true,
        ]);

        $cart = app(TaxEngine::class)->apply($cart, $address);

        $this->assertSame('20.00', $cart->tax_total);
        $this->assertTrue($cart->tax_inclusive);
        $this->assertSame('120.00', $cart->grand_total);
    }

    public function test_non_taxable_variant_does_not_receive_tax(): void
    {
        $store = $this->makeStore('Tax Exempt Store');
        $this->setCurrentStore($store);
        $cart = $this->cartWithVariant('100.00', false);
        $address = $this->makeAddress('exempt-tax@example.com');
        TaxRate::query()->create([
            'name' => 'KDV %20',
            'country_code' => 'TR',
            'rate' => '20.00',
        ]);

        $cart = app(TaxEngine::class)->apply($cart, $address);

        $this->assertSame('0.00', $cart->tax_total);
        $this->assertSame('100.00', $cart->grand_total);
    }

    public function test_checkout_tax_snapshot_uses_server_side_cart_tax_calculation(): void
    {
        $store = $this->makeStore('Checkout Tax Store');
        $this->setCurrentStore($store);
        $cart = $this->cartWithVariant('100.00');
        TaxRate::query()->create([
            'name' => 'KDV %20',
            'country_code' => 'TR',
            'rate' => '20.00',
        ]);
        $checkoutManager = app(CheckoutManager::class);
        $checkout = $checkoutManager->start($cart);
        $checkout = $checkoutManager->provideCustomerInformation(
            $checkout,
            new UpsertCustomerData(email: 'checkout-tax@example.com'),
        );
        $checkout = $checkoutManager->setAddresses(
            $checkout,
            $this->makeAddress('checkout-tax@example.com'),
        );

        $checkout = $checkoutManager->applyTax($checkout);

        $this->assertSame('20.00', $checkout->tax_total);
        $this->assertFalse($checkout->tax_inclusive);
        $this->assertSame('120.00', $checkout->grand_total);
    }

    private function cartWithVariant(string $price, bool $isTaxable = true): Cart
    {
        $product = Product::query()->create([
            'title' => 'Tax Product '.str()->random(8),
            'slug' => 'tax-product-'.str()->random(12),
            'status' => ProductStatus::Active,
            'is_taxable' => $isTaxable,
        ]);
        $variant = $product->variants()->create([
            'title' => 'Default',
            'price' => $price,
            'status' => ProductStatus::Active,
            'is_taxable' => $isTaxable,
        ]);
        $cartManager = app(CartManager::class);
        $cart = $cartManager->getOrCreate();
        $cartManager->addItem($cart, $variant, 1);

        return $cart->fresh();
    }

    private function makeAddress(string $email)
    {
        $customerManager = app(CustomerManager::class);
        $customer = $customerManager->findOrCreate(new UpsertCustomerData(email: $email));

        return $customerManager->createAddress($customer, new CustomerAddressData(
            type: CustomerAddressType::Shipping,
            firstName: 'Ahmet',
            lastName: 'Yilmaz',
            countryCode: 'TR',
            addressLine1: 'Ataturk Mahallesi',
            province: 'Bursa',
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