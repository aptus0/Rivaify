<?php

namespace Tests\Feature\Commerce;

use App\Core\Tenancy\CurrentStore;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Commerce\DTOs\Customer\CustomerAddressData;
use Modules\Commerce\DTOs\Customer\UpsertCustomerData;
use Modules\Commerce\Enums\Customer\CustomerAddressType;
use Modules\Commerce\Enums\Catalog\ProductStatus;
use Modules\Commerce\Enums\Discount\DiscountConditionType;
use Modules\Commerce\Enums\Discount\DiscountType;
use Modules\Commerce\Exceptions\Discount\DiscountNotApplicableException;
use Modules\Commerce\Models\Cart\Cart;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Models\Discount\Discount;
use Modules\Commerce\Models\Shipping\ShippingMethod;
use Modules\Commerce\Models\Tax\TaxRate;
use Modules\Commerce\Services\Cart\CartManager;
use Modules\Commerce\Services\Checkout\CheckoutManager;
use Modules\Commerce\Services\Customer\CustomerManager;
use Modules\Commerce\Services\Discount\DiscountEngine;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Models\Store;
use Tests\TestCase;

class DiscountEngineTest extends TestCase
{
    use DatabaseTransactions;

    public function test_welcome_ten_applies_a_server_calculated_percentage_discount(): void
    {
        $store = $this->makeStore('Discount Percentage Store');
        $this->setCurrentStore($store);
        $cart = $this->cartWithProducts([['price' => '4499.00', 'quantity' => 1]])['cart'];
        Discount::query()->create([
            'name' => 'Welcome 10',
            'code' => 'WELCOME10',
            'type' => DiscountType::Percentage,
            'value' => '10.00',
        ]);

        $cart = app(DiscountEngine::class)->apply($cart, 'welcome10');

        $this->assertSame('449.90', $cart->discount_total);
        $this->assertSame('4049.10', $cart->grand_total);
        $this->assertSame('WELCOME10', $cart->discount_code);
        $this->assertSame('449.90', $cart->items()->sole()->discount_amount);
    }

    public function test_product_scoped_fixed_discount_only_reduces_eligible_items(): void
    {
        $store = $this->makeStore('Discount Product Store');
        $this->setCurrentStore($store);
        $data = $this->cartWithProducts([
            ['price' => '100.00', 'quantity' => 1],
            ['price' => '200.00', 'quantity' => 1],
        ]);
        $discount = Discount::query()->create([
            'name' => 'Product Savings',
            'code' => 'PRODUCT50',
            'type' => DiscountType::FixedAmount,
            'value' => '50.00',
        ]);
        $discount->conditions()->create([
            'type' => DiscountConditionType::Products,
            'value' => ['product_ids' => [$data['products'][0]->id]],
        ]);

        $cart = app(DiscountEngine::class)->apply($data['cart'], 'PRODUCT50');
        $items = $cart->items()->orderBy('product_id')->get()->keyBy('product_id');

        $this->assertSame('50.00', $cart->discount_total);
        $this->assertSame('250.00', $cart->grand_total);
        $this->assertSame('50.00', $items[$data['products'][0]->id]->discount_amount);
        $this->assertSame('0.00', $items[$data['products'][1]->id]->discount_amount);
    }

    public function test_discount_below_minimum_purchase_is_rejected(): void
    {
        $store = $this->makeStore('Discount Minimum Store');
        $this->setCurrentStore($store);
        $cart = $this->cartWithProducts([['price' => '999.99', 'quantity' => 1]])['cart'];
        Discount::query()->create([
            'name' => 'Minimum Purchase',
            'code' => 'MIN1000',
            'type' => DiscountType::Percentage,
            'value' => '10.00',
            'minimum_purchase' => '1000.00',
        ]);

        $this->expectException(DiscountNotApplicableException::class);

        app(DiscountEngine::class)->apply($cart, 'MIN1000');
    }

    public function test_free_shipping_discount_recalculates_selected_checkout_shipping_total(): void
    {
        $store = $this->makeStore('Discount Free Shipping Store');
        $this->setCurrentStore($store);
        $cart = $this->cartWithProducts([['price' => '500.00', 'quantity' => 2]])['cart'];
        Discount::query()->create([
            'name' => 'Free Shipping',
            'code' => 'FREESHIP',
            'type' => DiscountType::FreeShipping,
            'value' => '0.00',
        ]);
        $checkoutManager = app(CheckoutManager::class);
        $checkout = $checkoutManager->start($cart);
        $checkout = $checkoutManager->provideCustomerInformation(
            $checkout,
            new UpsertCustomerData(email: 'shipping-discount@example.com'),
        );
        $address = $this->makeAddress('shipping-discount@example.com');
        $checkout = $checkoutManager->setAddresses($checkout, $address);
        $method = ShippingMethod::query()->create(['name' => 'Standart Kargo', 'price' => '49.00']);
        $checkout = $checkoutManager->selectShipping($checkout, $method);

        $checkout = $checkoutManager->applyDiscount($checkout, 'FREESHIP');

        $this->assertSame('0.00', $checkout->shipping_total);
        $this->assertSame('1000.00', $checkout->grand_total);
        $this->assertSame('FREESHIP', $checkout->discount_code);
    }

    public function test_checkout_discount_recalculates_tax_after_the_discount_is_applied(): void
    {
        $store = $this->makeStore('Discount Tax Recalculation Store');
        $this->setCurrentStore($store);
        $cart = $this->cartWithProducts([['price' => '100.00', 'quantity' => 1]])['cart'];
        TaxRate::query()->create([
            'name' => 'KDV %20',
            'country_code' => 'TR',
            'rate' => '20.00',
        ]);
        Discount::query()->create([
            'name' => 'Welcome 10',
            'code' => 'WELCOME10',
            'type' => DiscountType::Percentage,
            'value' => '10.00',
        ]);
        $checkoutManager = app(CheckoutManager::class);
        $checkout = $checkoutManager->start($cart);
        $checkout = $checkoutManager->provideCustomerInformation(
            $checkout,
            new UpsertCustomerData(email: 'discount-tax@example.com'),
        );
        $checkout = $checkoutManager->setAddresses($checkout, $this->makeAddress('discount-tax@example.com'));
        $checkout = $checkoutManager->applyTax($checkout);

        $checkout = $checkoutManager->applyDiscount($checkout, 'WELCOME10');

        $this->assertSame('10.00', $checkout->discount_total);
        $this->assertSame('18.00', $checkout->tax_total);
        $this->assertSame('108.00', $checkout->grand_total);
    }

    /**
     * @param  array<int, array{price: string, quantity: int}>  $lines
     * @return array{cart: Cart, products: array<int, Product>}
     */
    private function cartWithProducts(array $lines): array
    {
        $cartManager = app(CartManager::class);
        $cart = $cartManager->getOrCreate();
        $products = [];

        foreach ($lines as $line) {
            $product = Product::query()->create([
                'title' => 'Discount Product '.str()->random(8),
                'slug' => 'discount-product-'.str()->random(12),
                'status' => ProductStatus::Active,
            ]);
            $variant = $product->variants()->create([
                'title' => 'Default',
                'price' => $line['price'],
                'status' => ProductStatus::Active,
            ]);
            $cartManager->addItem($cart, $variant, $line['quantity']);
            $products[] = $product;
        }

        return ['cart' => $cart->fresh(), 'products' => $products];
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