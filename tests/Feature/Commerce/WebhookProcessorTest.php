<?php

namespace Tests\Feature\Commerce;

use App\Core\Tenancy\CurrentStore;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Modules\Commerce\DTOs\Customer\CustomerAddressData;
use Modules\Commerce\DTOs\Customer\UpsertCustomerData;
use Modules\Commerce\Enums\Checkout\CheckoutState;
use Modules\Commerce\Enums\Customer\CustomerAddressType;
use Modules\Commerce\Enums\Catalog\ProductStatus;
use Modules\Commerce\Enums\Payment\PaymentStatus;
use Modules\Commerce\Models\Catalog\Product;
use Modules\Commerce\Models\Checkout\CheckoutSession;
use Modules\Commerce\Models\Inventory\InventoryLevel;
use Modules\Commerce\Models\Order\Order;
use Modules\Commerce\Models\Payment\Payment;
use Modules\Commerce\Models\Payment\WebhookEvent;
use Modules\Commerce\Models\Shipping\ShippingMethod;
use Modules\Commerce\Services\Cart\CartManager;
use Modules\Commerce\Services\Checkout\CheckoutManager;
use Modules\Commerce\Services\Customer\CustomerManager;
use Modules\Commerce\Services\Inventory\InventoryManager;
use Modules\Commerce\Services\Payment\PaymentManager;
use Modules\Commerce\Services\Payment\WebhookInbox;
use Modules\Commerce\Services\Payment\WebhookProcessor;
use Modules\Commerce\Jobs\Payment\ProcessPaymentWebhook;
use Modules\Commerce\StateMachine\Checkout\CheckoutStateMachine;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Models\Store;
use Tests\TestCase;

class WebhookProcessorTest extends TestCase
{
    use DatabaseTransactions;

    public function test_duplicate_webhook_event_completes_only_one_order(): void
    {
        $store = $this->makeStore('Webhook Dedup Store');
        $this->setCurrentStore($store);
        [$payment, $level] = $this->paidPaymentAwaitingWebhook('webhook@example.com', 5);
        $payload = $this->payload($payment, 'evt_duplicate_1');
        $inbox = app(WebhookInbox::class);

        $event = $inbox->receive('manual', $payload);
        $this->assertTrue($event->wasRecentlyCreated);
        app(WebhookProcessor::class)->process($event);
        $duplicate = $inbox->receive('manual', $payload);
        $this->assertFalse($duplicate->wasRecentlyCreated);
        app(WebhookProcessor::class)->process($duplicate);

        $this->setCurrentStore($store);
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, Payment::query()->count());
        $this->assertNotNull($payment->fresh()->order_id);
        $this->assertSame(4, $level->fresh()->available_quantity);
        $this->assertSame(0, $level->fresh()->reserved_quantity);
    }

    public function test_webhook_endpoint_deduplicates_inbox_records_and_queues_only_the_first_delivery(): void
    {
        $store = $this->makeStore('Webhook Endpoint Store');
        $this->setCurrentStore($store);
        [$payment] = $this->paidPaymentAwaitingWebhook('endpoint@example.com', 5);
        $payload = $this->payload($payment, 'evt_endpoint_duplicate');
        Queue::fake();

        $this->postJson('/webhooks/payments/manual', $payload)
            ->assertOk()
            ->assertJsonPath('received', true);
        $this->postJson('/webhooks/payments/manual', $payload)
            ->assertOk()
            ->assertJsonPath('received', true);

        $this->assertSame(1, WebhookEvent::query()->count());
        Queue::assertPushed(ProcessPaymentWebhook::class, 1);
    }

    public function test_mismatched_webhook_amount_marks_payment_failed_and_releases_stock(): void
    {
        $store = $this->makeStore('Webhook Amount Store');
        $this->setCurrentStore($store);
        [$payment, $level, $checkout] = $this->paidPaymentAwaitingWebhook('amount@example.com', 5);
        $payload = $this->payload($payment, 'evt_amount_mismatch');
        $payload['amount'] = '1.00';

        $event = app(WebhookInbox::class)->receive('manual', $payload);
        app(WebhookProcessor::class)->process($event);

        $this->setCurrentStore($store);
        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
        $this->assertSame(CheckoutState::Failed, $checkout->fresh()->status);
        $this->assertSame(0, Order::query()->count());
        $this->assertSame(5, $level->fresh()->available_quantity);
        $this->assertSame(0, $level->fresh()->reserved_quantity);
    }

    /**
     * @return array{0: Payment, 1: InventoryLevel, 2: CheckoutSession}
     */
    private function paidPaymentAwaitingWebhook(string $email, int $stock): array
    {
        $product = Product::query()->create([
            'title' => 'Webhook Product '.str()->random(8),
            'slug' => 'webhook-product-'.str()->random(12),
            'status' => ProductStatus::Active,
        ]);
        $variant = $product->variants()->create([
            'title' => 'Default',
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
        $checkout = $checkoutManager->provideCustomerInformation($checkout, new UpsertCustomerData(email: $email));
        $checkout = $checkoutManager->setAddresses($checkout, $this->makeAddress($email));
        $method = ShippingMethod::query()->create(['name' => 'Standart Kargo', 'price' => '49.00']);
        $checkout = $checkoutManager->selectShipping($checkout, $method);
        $inventory->reserveForCheckout($checkout);
        $checkout = app(CheckoutStateMachine::class)->transition($checkout, CheckoutState::Payment);
        $payment = app(PaymentManager::class)->createPayment($checkout, 'manual', 'card');

        return [$payment, $level, $checkout];
    }

    /**
     * @return array<string, string>
     */
    private function payload(Payment $payment, string $eventId): array
    {
        return [
            'event_id' => $eventId,
            'type' => 'payment.updated',
            'provider_payment_id' => $payment->provider_payment_id,
            'status' => 'paid',
            'amount' => $payment->amount,
            'currency' => $payment->currency,
        ];
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