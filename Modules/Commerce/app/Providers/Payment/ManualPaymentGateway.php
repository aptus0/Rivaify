<?php

namespace Modules\Commerce\Providers\Payment;

use Illuminate\Support\Str;
use Modules\Commerce\Contracts\Payment\PaymentGateway;
use Modules\Commerce\DTOs\Payment\GatewayPaymentRequest;
use Modules\Commerce\DTOs\Payment\GatewayPaymentResult;
use Modules\Commerce\DTOs\Payment\VerifiedPaymentWebhook;
use Modules\Commerce\Enums\Payment\PaymentStatus;
use Modules\Commerce\ValueObjects\Money;

class ManualPaymentGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'manual';
    }

    public function createPayment(GatewayPaymentRequest $request): GatewayPaymentResult
    {
        return $this->result($request);
    }

    public function authorize(GatewayPaymentRequest $request): GatewayPaymentResult
    {
        return $this->result($request, PaymentStatus::Authorized);
    }

    public function capture(string $providerPaymentId, GatewayPaymentRequest $request): GatewayPaymentResult
    {
        return $this->result($request, PaymentStatus::Paid, $providerPaymentId);
    }

    public function cancel(string $providerPaymentId, GatewayPaymentRequest $request): GatewayPaymentResult
    {
        return $this->result($request, PaymentStatus::Voided, $providerPaymentId);
    }

    public function refund(string $providerPaymentId, GatewayPaymentRequest $request): GatewayPaymentResult
    {
        return $this->result($request, PaymentStatus::Refunded, $providerPaymentId);
    }

    public function verifyWebhook(array $payload, array $headers = []): VerifiedPaymentWebhook
    {
        return new VerifiedPaymentWebhook(
            externalEventId: (string) ($payload['event_id'] ?? throw new \InvalidArgumentException('Manual webhook event_id is required.')),
            type: (string) ($payload['type'] ?? 'payment.updated'),
            providerPaymentId: (string) ($payload['provider_payment_id'] ?? throw new \InvalidArgumentException('Manual webhook provider_payment_id is required.')),
            status: PaymentStatus::from((string) ($payload['status'] ?? throw new \InvalidArgumentException('Manual webhook status is required.'))),
            amount: Money::fromDecimal((string) ($payload['amount'] ?? throw new \InvalidArgumentException('Manual webhook amount is required.')), (string) ($payload['currency'] ?? 'TRY')),
            metadata: $payload,
        );
    }

    private function result(
        GatewayPaymentRequest $request,
        PaymentStatus $successStatus = PaymentStatus::Paid,
        ?string $providerPaymentId = null,
    ): GatewayPaymentResult {
        $providerPaymentId ??= 'manual_'.Str::ulid();
        if ($request->paymentMethodType === 'fail') {
            return new GatewayPaymentResult(
                providerPaymentId: $providerPaymentId,
                providerTransactionId: 'manual_tx_'.Str::ulid(),
                status: PaymentStatus::Failed,
                failureCode: 'manual_failure',
                failureMessage: 'Manual gateway simulated a payment failure.',
            );
        }

        return new GatewayPaymentResult(
            providerPaymentId: $providerPaymentId,
            providerTransactionId: 'manual_tx_'.Str::ulid(),
            status: $successStatus,
            metadata: ['reference' => $request->reference],
        );
    }
}