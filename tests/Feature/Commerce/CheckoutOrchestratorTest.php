<?php

namespace Tests\Feature\Commerce;

use App\Core\Tenancy\CurrentStore;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Commerce\DTOs\Customer\CustomerAddressData;
use Modules\Commerce\DTOs\Customer\UpsertCustomerData;
use Modules\Commerce\Enums\Cart\CartStatus;
use Modules\Commerce\Enums\Checkout\CheckoutState;
use Modules\Commerce\Enums\Customer\CustomerAddressType;
use Modules\Commerce\Enums\Catalog\ProductStatus;
use Modules\Commerce\Enums\Order\PaymentStatus as OrderPaymentStatus;
use Modules\Commerce\Enums\Payment\PaymentStatus;
use Modules\Commerce\Enums\Payment\PaymentTransactionStatus;
use Modules\Commerce\Enums\Payment\PaymentTransactionType;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Models\Checkout\CheckoutSession;
use Modules\Commerce\Models\Inventory\InventoryLevel;
use Modules\Commerce\Models\Order\Order;
use Modules\Commerce\Models\Order\OrderNotificationOutbox;
use Modules\Commerce\Models\Payment\Payment;
use Modules\Commerce\Models\Shipping\ShippingMethod;
use Modules\Commerce\Services\Cart\CartManager;
use Modules\Commerce\Services\Checkout\CheckoutManager;
use Modules\Commerce\Services\Checkout\CheckoutOrchestrator;
use Modules\Commerce\Services\Customer\CustomerManager;
use Modules\Commerce\Services\Inventory\InventoryManager;
use Modules\Commerce\Services\Payment\PaymentManager;
use Modules\Commerce\ValueObjects\Money;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Models\Store;
use Tests\TestCase;

class CheckoutOrchestratorTest extends TestCase
{
    use DatabaseTransactions;

    public function test_paid_checkout_creates_one_order_and_commits_reserved_inventory(): void
    {
        $store = $this->makeStore('Checkout Success Store');
        $this->setCurrentStore($store);
        [$checkout, $level] = $this->readyCheckout('paid@example.com', 5);
        $orchestrator = app(CheckoutOrchestrator::class);

        $payment = $orchestrator->pay($checkout, 'manual', 'card', 'payment-success-key');
        $repeatedPayment = $orchestrator->pay($checkout, 'manual', 'card', 'payment-success-key');

        $this->assertSame($payment->id, $repeatedPayment->id);
        $this->assertSame(PaymentStatus::Paid, $payment->status);
        $this->assertNotNull($payment->order_id);
        $this->assertSame(OrderPaymentStatus::Paid, $payment->order->payment_status);
        $this->assertSame(CheckoutState::Completed, $checkout->fresh()->status);
        $this->assertSame(CartStatus::Converted, $checkout->fresh()->cart->status);
        $this->assertSame(4, $level->fresh()->available_quantity);
        $this->assertSame(0, $level->fresh()->reserved_quantity);
        $this->assertSame(1, Payment::query()->count());
        $this->assertSame(1, Order::query()->count());
    }

    public function test_failed_payment_marks_checkout_failed_and_releases_reserved_inventory(): void
    {
        $store = $this->makeStore('Checkout Failure Store');
        $this->setCurrentStore($store);
        [$checkout, $level] = $this->readyCheckout('failed@example.com', 5);

        $payment = app(CheckoutOrchestrator::class)->pay($checkout, 'manual', 'fail', 'payment-failure-key');

        $this->assertSame(PaymentStatus::Failed, $payment->status);
        $this->assertNull($payment->order_id);
        $this->assertSame(CheckoutState::Failed, $checkout->fresh()->status);
        $this->assertSame(5, $level->fresh()->available_quantity);
        $this->assertSame(0, $level->fresh()->reserved_quantity);
        $this->assertSame(0, Order::query()->count());
    }

    public function test_payment_revalidates_current_variant_price_before_creating_the_order(): void
    {
        $store = $this->makeStore('Checkout Price Refresh Store');
        $this->setCurrentStore($store);
        [$checkout] = $this->readyCheckout('price-refresh@example.com', 5);
        $variant = $checkout->cart->items()->sole()->variant;
        $variant->update(['price' => '120.00']);

        $payment = app(CheckoutOrchestrator::class)->pay($checkout, 'manual', 'card', 'price-refresh-key');

        $this->assertSame('169.00', $payment->amount);
        $this->assertSame('169.00', $payment->order->grand_total);
        $this->assertSame('120.00', $payment->order->items()->sole()->unit_price);
    }

    public function test_paid_payment_supports_partial_and_full_refunds_with_transaction_history(): void
    {
        $store = $this->makeStore('Checkout Refund Store');
        $this->setCurrentStore($store);
        [$checkout] = $this->readyCheckout('refund@example.com', 5);
        $payment = app(CheckoutOrchestrator::class)->pay($checkout, 'manual', 'card', 'refund-payment-key');
        $payments = app(PaymentManager::class);

        $payment = $payments->refund($payment, Money::fromDecimal('50.00', 'TRY'));

        $this->assertSame(PaymentStatus::PartiallyRefunded, $payment->status);
        $this->assertSame(OrderPaymentStatus::PartiallyRefunded, $payment->order->fresh()->payment_status);
        $this->assertSame(1, $payment->transactions()
            ->where('type', PaymentTransactionType::Refund->value)
            ->where('status', PaymentTransactionStatus::Succeeded->value)
            ->count());
        $this->assertTrue(OrderNotificationOutbox::query()
            ->where('order_id', $payment->order_id)
            ->where('type', 'customer_refund_confirmation')
            ->exists());

        $payment = $payments->refund($payment, Money::fromDecimal('99.00', 'TRY'));

        $this->assertSame(PaymentStatus::Refunded, $payment->status);
        $this->assertSame(OrderPaymentStatus::Refunded, $payment->order->fresh()->payment_status);
        $this->assertSame(2, $payment->transactions()
            ->where('type', PaymentTransactionType::Refund->value)
            ->where('status', PaymentTransactionStatus::Succeeded->value)
            ->count());
    }

    /**
     * @return array{0: CheckoutSession, 1: InventoryLevel}
     */
    private function readyCheckout(string $email, int $stock): array
    {
        $product = Product::query()->create([
            'title' => 'Checkout Product '.str()->random(8),
            'slug' => 'checkout-product-'.str()->random(12),
            'status' => ProductStatus::Active,
        ]);
        $variant = $product->variants()->create([
            'title' => 'Black / 42',
            'price' => '100.00',
            'status' => ProductStatus::Active,
        ]);
        $inventory = app(InventoryManager::class);
        $location = $inventory->createLocation('Karacabey Depo');
        $level = $inventory->setAvailable($variant, $location, $stock);
        $cartManager = app(CartManager::class);
        $cart = $cartManager->getOrCreate();
        $cartManager->addItem($cart, $variant, 1);
        $checkoutManager = app(CheckoutManager::class);
        $checkout = $checkoutManager->start($cart);
        $checkout = $checkoutManager->provideCustomerInformation(
            $checkout,
            new UpsertCustomerData(email: $email, firstName: 'Ahmet', lastName: 'Yilmaz'),
        );
        $address = $this->makeAddress($email);
        $checkout = $checkoutManager->setAddresses($checkout, $address);
        $method = ShippingMethod::query()->create(['name' => 'Standart Kargo', 'price' => '49.00']);

        return [$checkoutManager->selectShipping($checkout, $method), $level];
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