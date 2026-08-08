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
use Modules\Commerce\Enums\Order\FulfillmentStatus;
use Modules\Commerce\Enums\Order\OrderStatus;
use Modules\Commerce\Enums\Order\PaymentStatus;
use Modules\Commerce\Models\Cart\Cart;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Models\Checkout\CheckoutSession;
use Modules\Commerce\Models\Order\Order;
use Modules\Commerce\Models\Order\OrderNotificationOutbox;
use Modules\Commerce\Models\Shipping\ShippingMethod;
use Modules\Commerce\Models\Tax\TaxRate;
use Modules\Commerce\Services\Cart\CartManager;
use Modules\Commerce\Services\Checkout\CheckoutManager;
use Modules\Commerce\Services\Customer\CustomerManager;
use Modules\Commerce\Services\Order\OrderCreator;
use Modules\Commerce\StateMachine\Checkout\CheckoutStateMachine;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Models\Store;
use Tests\TestCase;

class OrderCreatorTest extends TestCase
{
    use DatabaseTransactions;

    public function test_it_creates_an_immutable_order_snapshot_and_updates_customer_stats_once(): void
    {
        $store = $this->makeStore('Order Snapshot Store');
        $this->setCurrentStore($store);
        [$checkout, $product, $address] = $this->processingCheckout('ahmet@example.com');

        $order = app(OrderCreator::class)->create($checkout);
        $sameOrder = app(OrderCreator::class)->create($checkout);

        $this->assertSame($order->id, $sameOrder->id);
        $this->assertSame('RV-1001', $order->order_number);
        $this->assertSame(OrderStatus::Open, $order->status);
        $this->assertSame(PaymentStatus::Pending, $order->payment_status);
        $this->assertSame(FulfillmentStatus::Unfulfilled, $order->fulfillment_status);
        $this->assertSame('169.00', $order->grand_total);
        $this->assertSame('Nike Air Max', $order->items()->sole()->product_title);
        $this->assertSame('100.00', $order->items()->sole()->unit_price);
        $this->assertSame('120.00', $order->items()->sole()->line_total);
        $this->assertSame('Ataturk Mahallesi', $order->addresses()->where('type', 'shipping')->sole()->address_line_1);
        $this->assertSame('KDV %20', $order->taxLines()->sole()->name);
        $this->assertSame('20.00', $order->taxLines()->sole()->amount);
        $this->assertSame(1, $order->events()->count());
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(
            ['customer_order_confirmation', 'merchant_new_order'],
            OrderNotificationOutbox::query()->orderBy('type')->pluck('type')->all(),
        );
        $this->assertSame(1, $order->customer->fresh()->total_orders);
        $this->assertSame('169.00', $order->customer->fresh()->total_spent);

        $product->update(['title' => 'Nike Air Max 2027']);
        $product->variants()->sole()->update(['price' => '5900.00']);
        $address->update(['address_line_1' => 'Yeni Adres']);

        $this->assertSame('Nike Air Max', $order->items()->sole()->product_title);
        $this->assertSame('100.00', $order->items()->sole()->unit_price);
        $this->assertSame('Ataturk Mahallesi', $order->addresses()->where('type', 'shipping')->sole()->address_line_1);
    }

    public function test_order_numbers_increment_per_store(): void
    {
        $store = $this->makeStore('Order Sequence Store');
        $this->setCurrentStore($store);

        [$firstCheckout] = $this->processingCheckout('first@example.com');
        [$secondCheckout] = $this->processingCheckout('second@example.com');

        $firstOrder = app(OrderCreator::class)->create($firstCheckout);
        $secondOrder = app(OrderCreator::class)->create($secondCheckout);

        $this->assertSame('RV-1001', $firstOrder->order_number);
        $this->assertSame('RV-1002', $secondOrder->order_number);
    }

    public function test_store_scope_prevents_access_to_another_stores_order(): void
    {
        $storeA = $this->makeStore('Order Store A');
        $storeB = $this->makeStore('Order Store B');
        $this->setCurrentStore($storeA);
        [$checkout] = $this->processingCheckout('tenant@example.com');
        $order = app(OrderCreator::class)->create($checkout);

        $this->setCurrentStore($storeB);

        $this->assertNull(Order::query()->find($order->id));
        $this->assertSame(0, Order::query()->count());
    }

    /**
     * @return array{0: CheckoutSession, 1: Product, 2: \Modules\Commerce\Models\Customer\CustomerAddress}
     */
    private function processingCheckout(string $email): array
    {
        $product = Product::query()->create([
            'title' => 'Nike Air Max',
            'slug' => 'nike-air-max-'.str()->random(10),
            'status' => ProductStatus::Active,
        ]);
        $variant = $product->variants()->create([
            'title' => 'Black / 42',
            'sku' => 'NIKE-42-'.str()->random(6),
            'price' => '100.00',
            'status' => ProductStatus::Active,
        ]);
        $cartManager = app(CartManager::class);
        $cart = $cartManager->getOrCreate();
        $cartManager->addItem($cart, $variant, 1);
        TaxRate::query()->create([
            'name' => 'KDV %20',
            'country_code' => 'TR',
            'rate' => '20.00',
        ]);
        $checkoutManager = app(CheckoutManager::class);
        $checkout = $checkoutManager->start($cart);
        $checkout = $checkoutManager->provideCustomerInformation(
            $checkout,
            new UpsertCustomerData(email: $email, firstName: 'Ahmet', lastName: 'Yilmaz'),
        );
        $address = $this->makeAddress($email);
        $checkout = $checkoutManager->setAddresses($checkout, $address);
        $checkout = $checkoutManager->applyTax($checkout);
        $method = ShippingMethod::query()->create(['name' => 'Standart Kargo', 'price' => '49.00']);
        $checkout = $checkoutManager->selectShipping($checkout, $method);
        $stateMachine = app(CheckoutStateMachine::class);
        $checkout = $stateMachine->transition($checkout, CheckoutState::Payment);
        $checkout = $stateMachine->transition($checkout, CheckoutState::Processing);

        return [$checkout, $product, $address];
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