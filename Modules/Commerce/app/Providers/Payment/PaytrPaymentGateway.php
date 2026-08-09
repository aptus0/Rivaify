<?php

namespace Modules\Commerce\Providers\Payment;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Modules\Commerce\Contracts\Payment\PaymentGateway;
use Modules\Commerce\DTOs\Payment\GatewayPaymentRequest;
use Modules\Commerce\DTOs\Payment\GatewayPaymentResult;
use Modules\Commerce\DTOs\Payment\VerifiedPaymentWebhook;
use Modules\Commerce\Enums\Payment\PaymentStatus;
use Modules\Commerce\Exceptions\Payment\PaymentGatewayRejectedException;
use Modules\Commerce\Exceptions\Payment\PaymentGatewayUncertainException;
use Modules\Commerce\ValueObjects\Money;

class PaytrPaymentGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'paytr';
    }

    public function createPayment(GatewayPaymentRequest $request): GatewayPaymentResult
    {
        $this->assertConfigured();
        $merchantOid = preg_replace('/[^A-Za-z0-9]/', '', $request->reference) ?: $request->reference;
        $email = $this->requiredMetadata($request, 'email', 100);
        $userName = $this->requiredMetadata($request, 'user_name', 60);
        $userAddress = $this->requiredMetadata($request, 'user_address', 400);
        $userPhone = $this->requiredMetadata($request, 'user_phone', 20);
        $amount = (string) $request->amount->amount;
        $basket = base64_encode(json_encode($request->metadata['basket'] ?? [], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        $noInstallment = config('commerce.payments.paytr.no_installment') ? '1' : '0';
        $maxInstallment = (string) config('commerce.payments.paytr.max_installment', 0);
        $testMode = config('commerce.payments.paytr.test_mode') ? '1' : '0';
        $currency = $request->amount->currency === 'TRY' ? 'TL' : $request->amount->currency;
        $fields = [
            'merchant_id' => (string) config('commerce.payments.paytr.merchant_id'),
            'user_ip' => (string) ($request->metadata['user_ip'] ?? '127.0.0.1'),
            'merchant_oid' => $merchantOid,
            'email' => $email,
            'payment_amount' => $amount,
            'user_basket' => $basket,
            'no_installment' => $noInstallment,
            'max_installment' => $maxInstallment,
            'currency' => $currency,
            'test_mode' => $testMode,
        ];
        $hashSource = implode('', array_values($fields)).(string) config('commerce.payments.paytr.merchant_salt');
        $fields['paytr_token'] = base64_encode(hash_hmac('sha256', $hashSource, (string) config('commerce.payments.paytr.merchant_key'), true));
        $fields += [
            'user_name' => $userName,
            'user_address' => $userAddress,
            'user_phone' => $userPhone,
            'merchant_ok_url' => (string) ($request->metadata['ok_url'] ?? ''),
            'merchant_fail_url' => (string) ($request->metadata['fail_url'] ?? ''),
            'timeout_limit' => (string) config('commerce.payments.paytr.timeout', 30),
            'debug_on' => config('commerce.payments.paytr.debug') ? '1' : '0',
            'lang' => 'tr',
        ];

        try {
            $response = Http::asForm()->timeout(20)->retry(2, 250)->post((string) config('commerce.payments.paytr.token_url'), $fields)->throw()->json();
        } catch (ConnectionException|RequestException $exception) {
            throw new \RuntimeException('PayTR bağlantısı kurulamadı.', previous: $exception);
        }
        if (! is_array($response) || ($response['status'] ?? null) !== 'success' || empty($response['token'])) {
            throw new \RuntimeException('PayTR token oluşturulamadı.');
        }
        $token = (string) $response['token'];

        return new GatewayPaymentResult(
            providerPaymentId: $merchantOid,
            providerTransactionId: 'paytr_pending_'.$merchantOid,
            status: PaymentStatus::Pending,
            metadata: ['iframe_token' => $token, 'iframe_url' => rtrim((string) config('commerce.payments.paytr.iframe_url'), '/').'/'.$token],
        );
    }

    public function authorize(GatewayPaymentRequest $request): GatewayPaymentResult
    {
        return $this->createPayment($request);
    }

    public function capture(string $providerPaymentId, GatewayPaymentRequest $request): GatewayPaymentResult
    {
        throw new \LogicException('PayTR capture is callback-driven.');
    }

    public function cancel(string $providerPaymentId, GatewayPaymentRequest $request): GatewayPaymentResult
    {
        throw new \LogicException('PayTR cancellation service is not configured.');
    }

    public function refund(string $providerPaymentId, GatewayPaymentRequest $request): GatewayPaymentResult
    {
        $this->assertConfigured();
        $merchantOid = trim($providerPaymentId);
        if ($merchantOid === '') {
            throw new \InvalidArgumentException('PayTR merchant order reference is required.');
        }

        $merchantId = (string) config('commerce.payments.paytr.merchant_id');
        $returnAmount = $request->amount->toDecimal();
        $referenceNo = $this->referenceNo((string) ($request->metadata['reference_no'] ?? $request->reference));
        $hashSource = $merchantId.$merchantOid.$returnAmount.(string) config('commerce.payments.paytr.merchant_salt');
        $fields = [
            'merchant_id' => $merchantId,
            'merchant_oid' => $merchantOid,
            'return_amount' => $returnAmount,
            'paytr_token' => base64_encode(hash_hmac(
                'sha256',
                $hashSource,
                (string) config('commerce.payments.paytr.merchant_key'),
                true,
            )),
            'reference_no' => $referenceNo,
        ];

        try {
            $response = Http::asForm()
                ->timeout(20)
                ->retry(2, 250)
                ->post((string) config('commerce.payments.paytr.refund_url'), $fields)
                ->throw()
                ->json();
        } catch (ConnectionException|RequestException $exception) {
            throw new PaymentGatewayUncertainException('PayTR refund result is unknown.', previous: $exception);
        }

        if (! is_array($response) || ! array_key_exists('status', $response)) {
            throw new PaymentGatewayUncertainException('PayTR refund result is unknown.');
        }
        if ($response['status'] !== 'success') {
            throw new PaymentGatewayRejectedException('PayTR refund was rejected.');
        }
        if (! isset($response['merchant_oid']) || ! hash_equals($merchantOid, (string) $response['merchant_oid'])) {
            throw new PaymentGatewayUncertainException('PayTR refund response could not be verified.');
        }
        if (! isset($response['return_amount'])) {
            throw new PaymentGatewayUncertainException('PayTR refund response could not be verified.');
        }
        try {
            $responseAmount = Money::fromDecimal((string) $response['return_amount'], $request->amount->currency);
        } catch (\InvalidArgumentException $exception) {
            throw new PaymentGatewayUncertainException('PayTR refund response could not be verified.', previous: $exception);
        }
        if ($responseAmount->amount !== $request->amount->amount) {
            throw new PaymentGatewayUncertainException('PayTR refund response could not be verified.');
        }
        if (! isset($response['reference_no']) || ! hash_equals($referenceNo, (string) $response['reference_no'])) {
            throw new PaymentGatewayUncertainException('PayTR refund response could not be verified.');
        }

        return new GatewayPaymentResult(
            providerPaymentId: $merchantOid,
            providerTransactionId: $referenceNo,
            status: PaymentStatus::Refunded,
            metadata: [
                'reference_no' => $referenceNo,
                'return_amount' => $returnAmount,
                'is_test' => (int) ($response['is_test'] ?? 0),
            ],
        );
    }

    public function verifyWebhook(array $payload, array $headers = []): VerifiedPaymentWebhook
    {
        $this->assertConfigured();
        foreach (['merchant_oid', 'status', 'total_amount', 'hash', 'payment_type', 'currency', 'payment_amount'] as $field) {
            if (! isset($payload[$field]) || trim((string) $payload[$field]) === '') {
                throw new \InvalidArgumentException("PayTR {$field} alanı eksik.");
            }
        }
        if (! in_array($payload['status'], ['success', 'failed'], true)) {
            throw new \InvalidArgumentException('PayTR callback durumu geçersiz.');
        }
        if (preg_match('/^\d+$/', (string) $payload['total_amount']) !== 1) {
            throw new \InvalidArgumentException('PayTR callback toplam tutarı geçersiz.');
        }
        $expected = base64_encode(hash_hmac('sha256', (string) $payload['merchant_oid'].config('commerce.payments.paytr.merchant_salt').$payload['status'].$payload['total_amount'], (string) config('commerce.payments.paytr.merchant_key'), true));
        if (! hash_equals($expected, (string) $payload['hash'])) {
            throw new \InvalidArgumentException('PayTR callback imzası geçersiz.');
        }
        $status = $payload['status'] === 'success' ? PaymentStatus::Paid : PaymentStatus::Failed;
        $currency = $this->callbackCurrency($payload);
        $paymentType = strtolower(trim((string) $payload['payment_type']));
        if (! in_array($paymentType, ['card', 'eft'], true)) {
            throw new \InvalidArgumentException('PayTR callback ödeme tipi geçersiz.');
        }
        if (preg_match('/^\d+$/', (string) $payload['payment_amount']) !== 1) {
            throw new \InvalidArgumentException('PayTR callback ödeme tutarı geçersiz.');
        }

        return new VerifiedPaymentWebhook(
            externalEventId: hash('sha256', 'paytr|'.(string) $payload['merchant_oid']),
            type: $status === PaymentStatus::Paid ? 'payment.succeeded' : 'payment.failed',
            providerPaymentId: (string) $payload['merchant_oid'],
            status: $status,
            amount: Money::fromMinor((int) $payload['total_amount'], $currency),
            metadata: [
                ...array_diff_key($payload, ['hash' => true]),
                'currency' => $currency,
                'total_amount' => (string) $payload['total_amount'],
                'payment_type' => $paymentType,
                'payment_amount' => (string) $payload['payment_amount'],
            ],
        );
    }

    private function requiredMetadata(GatewayPaymentRequest $request, string $field, int $maxLength): string
    {
        $value = trim((string) ($request->metadata[$field] ?? ''));
        if ($value === '') {
            throw new \InvalidArgumentException("PayTR {$field} metadata is required.");
        }

        return mb_substr($value, 0, $maxLength);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function callbackCurrency(array $payload): string
    {
        if (! isset($payload['currency']) || trim((string) $payload['currency']) === '') {
            throw new \InvalidArgumentException('PayTR callback para birimi eksik.');
        }

        $currency = strtoupper(trim((string) $payload['currency']));
        $currency = $currency === 'TL' ? 'TRY' : $currency;
        if (! in_array($currency, ['TRY', 'USD', 'EUR', 'GBP', 'RUB'], true)) {
            throw new \InvalidArgumentException('PayTR callback para birimi desteklenmiyor.');
        }

        return $currency;
    }

    private function referenceNo(string $reference): string
    {
        $reference = preg_replace('/[^A-Za-z0-9]/', '', $reference) ?? '';
        if ($reference === '') {
            $reference = hash('sha256', 'paytr-refund');
        }

        return substr($reference, 0, 64);
    }

    private function assertConfigured(): void
    {
        if (! config('commerce.payments.paytr.merchant_id') || ! config('commerce.payments.paytr.merchant_key') || ! config('commerce.payments.paytr.merchant_salt')) {
            throw new \RuntimeException('PayTR bilgileri yapılandırılmamış.');
        }
    }
}
