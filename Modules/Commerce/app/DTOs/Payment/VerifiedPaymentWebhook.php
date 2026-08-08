<?php

namespace Modules\Commerce\DTOs\Payment;

use Modules\Commerce\Enums\Payment\PaymentStatus;
use Modules\Commerce\ValueObjects\Money;

readonly class VerifiedPaymentWebhook
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $externalEventId,
        public string $type,
        public string $providerPaymentId,
        public PaymentStatus $status,
        public Money $amount,
        public array $metadata = [],
    ) {}
}