<?php

namespace Modules\Commerce\DTOs\Payment;

use Modules\Commerce\ValueObjects\Money;

readonly class GatewayPaymentRequest
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $reference,
        public Money $amount,
        public ?string $paymentMethodType = null,
        public array $metadata = [],
    ) {}
}