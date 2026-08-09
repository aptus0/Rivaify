<?php

namespace Tests\Feature\Commerce;

use App\Core\Tenancy\CurrentStore;
use App\Core\Tenancy\Scopes\StoreScope;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Modules\Commerce\Enums\Checkout\CheckoutState;
use Modules\Commerce\Enums\Order\PaymentStatus as OrderPaymentStatus;
use Modules\Commerce\Enums\Payment\PaymentStatus;
use Modules\Commerce\Enums\Payment\PaymentTransactionStatus;
use Modules\Commerce\Enums\Payment\PaymentTransactionType;
use Modules\Commerce\Models\Cart\Cart;
use Modules\Commerce\Models\Checkout\CheckoutSession;
use Modules\Commerce\Models\Order\Order;
use Modules\Commerce\Models\Payment\Payment;
use Modules\Commerce\Models\Payment\PaymentTransaction;
use Modules\Commerce\Models\Payment\WebhookEvent;
use Modules\Commerce\Providers\Payment\PaytrPaymentGateway;
use Modules\Commerce\Services\Payment\PaymentManager;
use Modules\Commerce\Services\Payment\WebhookInbox;
use Modules\Merchant\Models\Merchant;
use Modules\Store\Enums\StoreUserRole;
use Modules\Store\Enums\StoreUserStatus;
use Modules\Store\Models\Store;
use Modules\Store\Models\StoreUser;
use Tests\TestCase;

class PaytrPaymentFlowTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('commerce.payments.paytr', [
            'merchant_id' => 'merchant-id',
            'merchant_key' => 'merchant-key-super-secret',
            'merchant_salt' => 'merchant-salt-super-secret',
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
        $this->withHeader('Referer', 'https://app.rivaify.com');
    }

    public function test_payment_manager_accepts_higher_installment_total_but_rejects_underpayment(): void
    {
        [, $store] = $this->makeStoreWithUser('PayTR Callback Store');
        app(CurrentStore::class)->set($store);
        [, $installmentPayment] = $this->makePayment(
            $store,
            amount: '100.00',
            currency: 'EUR',
            providerPaymentId: 'INSTALLMENTORDER1',
        );
        [, $underpaidPayment] = $this->makePayment(
            $store,
            amount: '100.00',
            currency: 'USD',
            providerPaymentId: 'UNDERPAIDORDER1',
        );
        Http::fake();

        $gateway = app(PaytrPaymentGateway::class);
        $manager = app(PaymentManager::class);
        $installment = $manager->applyWebhook('paytr', $gateway->verifyWebhook(
            $this->callbackPayload('INSTALLMENTORDER1', '11200', '10000', 'EUR'),
        ));
        $underpaid = $manager->applyWebhook('paytr', $gateway->verifyWebhook(
            $this->callbackPayload('UNDERPAIDORDER1', '9900', '10000', 'USD'),
        ));
        $lateUnderpaidDuplicate = $manager->applyWebhook('paytr', $gateway->verifyWebhook(
            $this->callbackPayload('INSTALLMENTORDER1', '9900', '10000', 'EUR'),
        ));

        $this->assertSame(PaymentStatus::Paid, $installment->status);
        $this->assertSame('100.00', $installment->amount);
        $this->assertSame(PaymentStatus::Failed, $underpaid->status);
        $this->assertSame(PaymentStatus::Paid, $lateUnderpaidDuplicate->status);
        $this->assertNull($lateUnderpaidDuplicate->failure_code);
        $this->assertSame('amount_mismatch', $underpaid->failure_code);
        $this->assertSame(
            1,
            PaymentTransaction::query()
                ->where('payment_id', $installmentPayment->id)
                ->where('status', PaymentTransactionStatus::Succeeded->value)
                ->count(),
        );
        $this->assertSame(
            1,
            PaymentTransaction::query()
                ->where('payment_id', $underpaidPayment->id)
                ->where('status', PaymentTransactionStatus::Failed->value)
                ->count(),
        );
        Http::assertNothingSent();
    }

    public function test_paytr_callback_inbox_is_idempotent(): void
    {
        [, $store] = $this->makeStoreWithUser('PayTR Inbox Store');
        app(CurrentStore::class)->set($store);
        [, $payment] = $this->makePayment(
            $store,
            amount: '100.00',
            currency: 'GBP',
            providerPaymentId: 'IDEMPOTENTORDER1',
        );
        Http::fake();
        $payload = $this->callbackPayload(
            $payment->provider_payment_id,
            '10800',
            '10000',
            'GBP',
        );

        $duplicatePayload = $this->callbackPayload(
            $payment->provider_payment_id,
            '10900',
            '10000',
            'GBP',
        );

        $first = app(WebhookInbox::class)->receive('paytr', $payload);
        $duplicate = app(WebhookInbox::class)->receive('paytr', $duplicatePayload);

        $this->assertTrue($first->wasRecentlyCreated);
        $this->assertFalse($duplicate->wasRecentlyCreated);
        $this->assertSame($first->id, $duplicate->id);
        $this->assertSame('10800', $duplicate->payload['total_amount']);
        $this->assertSame(1, WebhookEvent::query()->where('provider', 'paytr')->count());
        Http::assertNothingSent();
    }

    public function test_paytr_callback_does_not_return_ok_when_processing_fails(): void
    {
        Http::fake();
        $payload = $this->callbackPayload('MISSINGPAYMENTORDER1', '10000', '10000', 'TL');

        $response = $this->withHeader('Referer', 'https://www.paytr.com')
            ->postJson('/webhooks/payments/paytr', $payload);

        $response->assertNotFound();
        $this->assertNotSame('OK', trim($response->getContent()));
        $event = WebhookEvent::query()->sole();
        $this->assertSame('failed', $event->status);
        $this->assertSame(1, $event->attempts);
        $this->assertNotNull($event->last_error);
        Http::assertNothingSent();
    }

    public function test_processed_duplicate_paytr_callback_returns_plain_ok_without_reprocessing(): void
    {
        Http::fake();
        $payload = $this->callbackPayload('PROCESSEDDUPLICATE1', '10000', '10000', 'TL');
        $verified = app(PaytrPaymentGateway::class)->verifyWebhook($payload);
        $event = WebhookEvent::query()->create([
            'provider' => 'paytr',
            'external_event_id' => $verified->externalEventId,
            'type' => $verified->type,
            'payload' => $payload,
            'status' => 'processed',
            'received_at' => now(),
            'processed_at' => now(),
        ]);

        $response = $this->withHeader('Referer', 'https://www.paytr.com')
            ->postJson('/webhooks/payments/paytr', $payload);

        $response->assertOk();
        $this->assertSame('OK', $response->getContent());
        $this->assertStringStartsWith('text/plain', (string) $response->headers->get('Content-Type'));
        $this->assertSame(0, $event->refresh()->attempts);
        $this->assertSame(1, WebhookEvent::query()->where('provider', 'paytr')->count());
        Http::assertNothingSent();
    }

    public function test_admin_paytr_refund_sends_request_and_updates_the_order(): void
    {
        [$user, $store] = $this->makeStoreWithUser('PayTR Refund Store');
        app(CurrentStore::class)->set($store);
        [$order, $payment] = $this->makePayment(
            $store,
            amount: '100.00',
            currency: 'TRY',
            providerPaymentId: 'REFUNDORDER1',
            paid: true,
            withOrder: true,
        );
        app(CurrentStore::class)->clear();
        Sanctum::actingAs($user);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Http::fake(function (Request $request) {
            return Http::response([
                'status' => 'success',
                'is_test' => 1,
                'merchant_oid' => $request['merchant_oid'],
                'return_amount' => $request['return_amount'],
                'reference_no' => $request['reference_no'],
            ]);
        });

        $this->withSession(['current_store_id' => $store->id])
            ->postJson(
                "/api/v1/orders/{$order->ulid}/payments/{$payment->ulid}/refund",
                ['amount' => '40.00'],
            )
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'partially_refunded')
            ->assertJsonPath('data.payments.0.status', 'partially_refunded')
            ->assertJsonPath('data.payments.0.refunded_amount', '40.00')
            ->assertJsonPath('data.payments.0.refundable_amount', '60.00');

        $transaction = PaymentTransaction::withoutGlobalScope(StoreScope::class)
            ->where('payment_id', $payment->id)
            ->where('type', PaymentTransactionType::Refund->value)
            ->where('status', PaymentTransactionStatus::Succeeded->value)
            ->sole();
        $this->assertSame('40.00', $transaction->amount);
        $this->assertMatchesRegularExpression('/^R[0-9A-HJKMNP-TV-Z]{26}$/', $transaction->provider_transaction_id);
        Http::assertSentCount(1);
    }

    public function test_failed_paytr_refund_is_recorded_and_admin_api_returns_an_error_without_secrets(): void
    {
        [$user, $store] = $this->makeStoreWithUser('PayTR Failed Refund Store');
        app(CurrentStore::class)->set($store);
        [$order, $payment] = $this->makePayment(
            $store,
            amount: '100.00',
            currency: 'TRY',
            providerPaymentId: 'FAILEDREFUNDORDER1',
            paid: true,
            withOrder: true,
        );
        app(CurrentStore::class)->clear();
        Sanctum::actingAs($user);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $referenceNumbers = [];
        Http::fake(function (Request $request) use (&$referenceNumbers) {
            $referenceNumbers[] = $request['reference_no'];

            return Http::response([
                'status' => 'error',
                'err_no' => '006',
                'err_msg' => 'merchant-key-super-secret must never escape',
            ]);
        });

        $response = $this->withSession(['current_store_id' => $store->id])
            ->postJson(
                "/api/v1/orders/{$order->ulid}/payments/{$payment->ulid}/refund",
                ['amount' => '25.00'],
            )
            ->assertStatus(502)
            ->assertExactJson(['message' => 'payment_refund_failed']);
        $response->assertDontSee('merchant-key-super-secret');
        $response->assertDontSee('merchant-salt-super-secret');
        $this->withSession(['current_store_id' => $store->id])
            ->postJson(
                "/api/v1/orders/{$order->ulid}/payments/{$payment->ulid}/refund",
                ['amount' => '25.00'],
            )
            ->assertStatus(502)
            ->assertExactJson(['message' => 'payment_refund_failed']);

        $transactions = PaymentTransaction::withoutGlobalScope(StoreScope::class)
            ->where('payment_id', $payment->id)
            ->where('type', PaymentTransactionType::Refund->value)
            ->where('status', PaymentTransactionStatus::Failed->value)
            ->get();
        $this->assertCount(2, $transactions);
        foreach ($transactions as $transaction) {
            $this->assertSame('gateway_rejected', $transaction->metadata['failure_code']);
            $this->assertSame(
                'Payment provider rejected the refund request.',
                $transaction->metadata['failure_message'],
            );
            $this->assertStringNotContainsString(
                'merchant-key-super-secret',
                json_encode($transaction->metadata, JSON_THROW_ON_ERROR),
            );
        }
        $this->assertCount(2, $referenceNumbers);
        $this->assertCount(2, array_unique($referenceNumbers));
        Http::assertSentCount(2);
    }

    public function test_unknown_refund_result_stays_pending_and_blocks_a_concurrent_retry(): void
    {
        [$user, $store] = $this->makeStoreWithUser('PayTR Unknown Refund Store');
        app(CurrentStore::class)->set($store);
        [$order, $payment] = $this->makePayment(
            $store,
            amount: '100.00',
            currency: 'TRY',
            providerPaymentId: 'UNKNOWNREFUNDORDER1',
            paid: true,
            withOrder: true,
        );
        app(CurrentStore::class)->clear();
        Sanctum::actingAs($user);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Http::fake([
            'https://paytr.test/refund' => Http::failedConnection(),
        ]);

        $this->withSession(['current_store_id' => $store->id])
            ->postJson(
                "/api/v1/orders/{$order->ulid}/payments/{$payment->ulid}/refund",
                ['amount' => '25.00'],
            )
            ->assertStatus(502)
            ->assertExactJson(['message' => 'payment_refund_failed']);

        $pending = PaymentTransaction::withoutGlobalScope(StoreScope::class)
            ->where('payment_id', $payment->id)
            ->where('type', PaymentTransactionType::Refund->value)
            ->where('status', PaymentTransactionStatus::Pending->value)
            ->sole();
        $this->assertTrue($pending->metadata['reconciliation_required']);
        $this->assertSame('gateway_result_unknown', $pending->metadata['failure_code']);
        $requestsAfterUnknownResult = Http::recorded()->count();
        $this->withSession(['current_store_id' => $store->id])
            ->getJson("/api/v1/orders/{$order->ulid}")
            ->assertOk()
            ->assertJsonPath('data.payments.0.refunded_amount', '0.00')
            ->assertJsonPath('data.payments.0.refundable_amount', '0.00');

        $this->withSession(['current_store_id' => $store->id])
            ->postJson(
                "/api/v1/orders/{$order->ulid}/payments/{$payment->ulid}/refund",
                ['amount' => '25.00'],
            )
            ->assertStatus(422)
            ->assertJsonPath('message', 'A refund is already in progress for this payment.');

        $this->assertSame($requestsAfterUnknownResult, Http::recorded()->count());
        $this->assertSame(
            1,
            PaymentTransaction::withoutGlobalScope(StoreScope::class)
                ->where('payment_id', $payment->id)
                ->where('type', PaymentTransactionType::Refund->value)
                ->count(),
        );
    }

    public function test_merchant_cannot_refund_another_stores_paytr_payment(): void
    {
        [$userA, $storeA] = $this->makeStoreWithUser('PayTR Tenant Store A');
        [, $storeB] = $this->makeStoreWithUser('PayTR Tenant Store B');
        app(CurrentStore::class)->set($storeB);
        [$foreignOrder, $foreignPayment] = $this->makePayment(
            $storeB,
            amount: '100.00',
            currency: 'TRY',
            providerPaymentId: 'FOREIGNREFUNDORDER1',
            paid: true,
            withOrder: true,
        );
        app(CurrentStore::class)->clear();
        Sanctum::actingAs($userA);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Http::fake();

        $this->withSession(['current_store_id' => $storeA->id])
            ->postJson(
                "/api/v1/orders/{$foreignOrder->ulid}/payments/{$foreignPayment->ulid}/refund",
                ['amount' => '25.00'],
            )
            ->assertNotFound();

        $this->assertSame(
            PaymentStatus::Paid,
            Payment::withoutGlobalScope(StoreScope::class)->findOrFail($foreignPayment->id)->status,
        );
        Http::assertNothingSent();
    }

    /**
     * @return array{0: User, 1: Store}
     */
    private function makeStoreWithUser(string $name): array
    {
        $user = User::factory()->create();
        $merchant = Merchant::create(['owner_user_id' => $user->id]);
        $store = $merchant->stores()->create([
            'name' => $name,
            'slug' => str($name.'-'.str()->random(6))->slug(),
        ]);
        StoreUser::withoutGlobalScope(StoreScope::class)->create([
            'store_id' => $store->id,
            'user_id' => $user->id,
            'role' => StoreUserRole::Owner,
            'status' => StoreUserStatus::Active,
            'joined_at' => now(),
        ]);

        return [$user, $store];
    }

    /**
     * @return array{0: Order|null, 1: Payment}
     */
    private function makePayment(
        Store $store,
        string $amount,
        string $currency,
        string $providerPaymentId,
        bool $paid = false,
        bool $withOrder = false,
    ): array {
        app(CurrentStore::class)->set($store);
        $cart = Cart::query()->create([
            'token' => 'paytr-cart-'.str()->ulid(),
            'currency' => $currency,
            'grand_total' => $amount,
        ]);
        $checkout = CheckoutSession::query()->create([
            'cart_id' => $cart->id,
            'token' => 'paytr-checkout-'.str()->ulid(),
            'status' => $paid ? CheckoutState::Completed : CheckoutState::Payment,
            'currency' => $currency,
            'grand_total' => $amount,
        ]);
        $order = $withOrder ? Order::query()->create([
            'checkout_id' => $checkout->id,
            'order_number' => 'RV-'.str()->upper(str()->random(10)),
            'currency' => $currency,
            'grand_total' => $amount,
            'payment_status' => OrderPaymentStatus::Paid,
            'placed_at' => now(),
        ]) : null;
        $payment = Payment::query()->create([
            'order_id' => $order?->id,
            'checkout_id' => $checkout->id,
            'provider' => 'paytr',
            'provider_payment_id' => $providerPaymentId,
            'status' => $paid ? PaymentStatus::Paid : PaymentStatus::Pending,
            'amount' => $amount,
            'currency' => $currency,
            'paid_at' => $paid ? now() : null,
        ]);

        return [$order, $payment];
    }

    /**
     * @return array<string, string>
     */
    private function callbackPayload(
        string $merchantOid,
        string $totalAmount,
        string $paymentAmount,
        string $currency,
    ): array {
        $status = 'success';

        return [
            'merchant_oid' => $merchantOid,
            'status' => $status,
            'total_amount' => $totalAmount,
            'payment_amount' => $paymentAmount,
            'payment_type' => 'card',
            'currency' => $currency,
            'hash' => base64_encode(hash_hmac(
                'sha256',
                $merchantOid.'merchant-salt-super-secret'.$status.$totalAmount,
                'merchant-key-super-secret',
                true,
            )),
        ];
    }
}
