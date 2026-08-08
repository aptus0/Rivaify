<?php

namespace Modules\Commerce\DTOs\Customer;

readonly class UpsertCustomerData
{
    public function __construct(
        public string $email,
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $phone = null,
        public ?bool $acceptsMarketing = null,
    ) {}
}