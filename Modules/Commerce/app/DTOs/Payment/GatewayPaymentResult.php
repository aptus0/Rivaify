<?php

namespace Modules\Commerce\DTOs\Payment;

use Modules\Commerce\Enums\Payment\PaymentStatus;

readonly class GatewayPaymentResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $providerPaymentId,
        public string $providerTransactionId,
        public PaymentStatus $status,
        public array $metadata = [],
        public ?string $failureCode = null,
        public ?string $failureMessage = null,
    ) {}
}