<?php

namespace Modules\Commerce\Contracts\Payment;

use Modules\Commerce\DTOs\Payment\GatewayPaymentRequest;
use Modules\Commerce\DTOs\Payment\GatewayPaymentResult;
use Modules\Commerce\DTOs\Payment\VerifiedPaymentWebhook;

interface PaymentGateway
{
    public function name(): string;

    public function createPayment(GatewayPaymentRequest $request): GatewayPaymentResult;

    public function authorize(GatewayPaymentRequest $request): GatewayPaymentResult;

    public function capture(string $providerPaymentId, GatewayPaymentRequest $request): GatewayPaymentResult;

    public function cancel(string $providerPaymentId, GatewayPaymentRequest $request): GatewayPaymentResult;

    public function refund(string $providerPaymentId, GatewayPaymentRequest $request): GatewayPaymentResult;

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $headers
     */
    public function verifyWebhook(array $payload, array $headers = []): VerifiedPaymentWebhook;
}