<?php

namespace Tests\Feature\Commerce;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Modules\Commerce\DTOs\Payment\GatewayPaymentRequest;
use Modules\Commerce\Enums\Payment\PaymentStatus;
use Modules\Commerce\Providers\Payment\PaytrPaymentGateway;
use Modules\Commerce\ValueObjects\Money;
use Tests\TestCase;

class PaytrPaymentGatewayTest extends TestCase
{
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
    }

    public function test_iframe_token_request_uses_the_official_hmac_and_does_not_return_credentials(): void
    {
        Http::fake([
            'https://paytr.test/get-token' => Http::response([
                'status' => 'success',
                'token' => 'iframe-token-value',
            ]),
        ]);

        $result = app(PaytrPaymentGateway::class)->createPayment(new GatewayPaymentRequest(
            reference: 'payment:01HZY-ABC',
            amount: Money::fromDecimal('123.45', 'USD'),
            paymentMethodType: 'card',
            metadata: [
                'user_ip' => '203.0.113.10',
                'email' => str_repeat('e', 110).'@example.com',
                'user_name' => str_repeat('N', 70),
                'user_phone' => '+'.str_repeat('5', 30),
                'user_address' => str_repeat('A', 450),
                'ok_url' => 'https://shop.test/success',
                'fail_url' => 'https://shop.test/failure',
                'basket' => [['Test product', '123.45', 1]],
            ],
        ));

        $this->assertSame(PaymentStatus::Pending, $result->status);
        $this->assertSame('payment01HZYABC', $result->providerPaymentId);
        $this->assertSame('https://paytr.test/secure/iframe-token-value', $result->metadata['iframe_url']);
        $serializedResult = json_encode($result->metadata, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('merchant-key-super-secret', $serializedResult);
        $this->assertStringNotContainsString('merchant-salt-super-secret', $serializedResult);

        Http::assertSent(function (Request $request): bool {
            $data = $request->data();
            $hashSource = implode('', [
                $data['merchant_id'],
                $data['user_ip'],
                $data['merchant_oid'],
                $data['email'],
                $data['payment_amount'],
                $data['user_basket'],
                $data['no_installment'],
                $data['max_installment'],
                $data['currency'],
                $data['test_mode'],
            ]).'merchant-salt-super-secret';
            $expectedToken = base64_encode(hash_hmac(
                'sha256',
                $hashSource,
                'merchant-key-super-secret',
                true,
            ));

            return $request->url() === 'https://paytr.test/get-token'
                && $request->method() === 'POST'
                && $data['merchant_oid'] === 'payment01HZYABC'
                && $data['payment_amount'] === '12345'
                && $data['currency'] === 'USD'
                && mb_strlen($data['email']) === 100
                && mb_strlen($data['user_name']) === 60
                && mb_strlen($data['user_phone']) === 20
                && mb_strlen($data['user_address']) === 400
                && hash_equals($expectedToken, $data['paytr_token']);
        });
    }

    public function test_success_callback_accepts_installment_total_and_supported_currencies(): void
    {
        Http::fake();
        $gateway = app(PaytrPaymentGateway::class);

        foreach ([
            ['TL', 'TRY'],
            ['USD', 'USD'],
            ['EUR', 'EUR'],
            ['GBP', 'GBP'],
        ] as $index => [$callbackCurrency, $expectedCurrency]) {
            $payload = $this->callbackPayload(
                merchantOid: 'ORDER'.($index + 1),
                totalAmount: '11200',
                paymentAmount: '10000',
                currency: $callbackCurrency,
            );
            $verified = $gateway->verifyWebhook($payload);

            $this->assertSame(PaymentStatus::Paid, $verified->status);
            $this->assertSame(11200, $verified->amount->amount);
            $this->assertSame($expectedCurrency, $verified->amount->currency);
            $this->assertSame('10000', $verified->metadata['payment_amount']);
            $this->assertArrayNotHasKey('hash', $verified->metadata);
        }

        Http::assertNothingSent();
    }

    public function test_callback_rejects_an_invalid_signature(): void
    {
        Http::fake();
        $payload = $this->callbackPayload('ORDERINVALID', '10000', '10000', 'TL');
        $payload['hash'] = 'invalid-signature';

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('PayTR callback imzası geçersiz.');

        app(PaytrPaymentGateway::class)->verifyWebhook($payload);
    }

    public function test_failed_callback_requires_all_official_fields(): void
    {
        Http::fake();
        $gateway = app(PaytrPaymentGateway::class);
        $payload = $this->callbackPayload('ORDERFAILED', '10000', '10000', 'TL');
        $payload['status'] = 'failed';
        $payload['hash'] = base64_encode(hash_hmac(
            'sha256',
            'ORDERFAILEDmerchant-salt-super-secretfailed10000',
            'merchant-key-super-secret',
            true,
        ));

        foreach (['payment_type', 'currency', 'payment_amount'] as $requiredField) {
            $invalidPayload = $payload;
            unset($invalidPayload[$requiredField]);

            try {
                $gateway->verifyWebhook($invalidPayload);
                $this->fail("Missing {$requiredField} callback field was accepted.");
            } catch (\InvalidArgumentException $exception) {
                $this->assertStringContainsString($requiredField, $exception->getMessage());
            }
        }

        Http::assertNothingSent();
    }

    public function test_refund_uses_the_official_hmac_amount_and_reference_number(): void
    {
        Http::fake(function (Request $request) {
            return Http::response([
                'status' => 'success',
                'is_test' => 1,
                'merchant_oid' => $request['merchant_oid'],
                'return_amount' => $request['return_amount'],
                'reference_no' => $request['reference_no'],
            ]);
        });

        $result = app(PaytrPaymentGateway::class)->refund(
            'ORDER123',
            new GatewayPaymentRequest(
                reference: 'refund:ORDER123',
                amount: Money::fromDecimal('42.75', 'EUR'),
                metadata: ['reference_no' => 'REF-123_test'],
            ),
        );

        $this->assertSame(PaymentStatus::Refunded, $result->status);
        $this->assertSame('ORDER123', $result->providerPaymentId);
        $this->assertSame('REF123test', $result->providerTransactionId);
        $this->assertSame('42.75', $result->metadata['return_amount']);

        Http::assertSent(function (Request $request): bool {
            $data = $request->data();
            $expectedToken = base64_encode(hash_hmac(
                'sha256',
                'merchant-idORDER12342.75merchant-salt-super-secret',
                'merchant-key-super-secret',
                true,
            ));

            return $request->url() === 'https://paytr.test/refund'
                && $request->method() === 'POST'
                && $data['merchant_id'] === 'merchant-id'
                && $data['merchant_oid'] === 'ORDER123'
                && $data['return_amount'] === '42.75'
                && $data['reference_no'] === 'REF123test'
                && hash_equals($expectedToken, $data['paytr_token']);
        });
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
