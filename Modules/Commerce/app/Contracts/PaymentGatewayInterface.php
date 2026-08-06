<?php

namespace Modules\Commerce\Contracts;

interface PaymentGatewayInterface
{
    public function initializePayment(array $paymentData): array;
    public function verifyCallback(array $callbackData): bool;
    public function refundTransaction(string $transactionId, float $amount): bool;
}
