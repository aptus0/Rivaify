<?php

namespace Modules\Commerce\DTOs\Customer;

use Modules\Commerce\Enums\Customer\CustomerAddressType;

readonly class CustomerAddressData
{
    public function __construct(
        public CustomerAddressType $type,
        public string $firstName,
        public string $lastName,
        public string $countryCode,
        public string $addressLine1,
        public ?string $company = null,
        public ?string $phone = null,
        public ?string $province = null,
        public ?string $district = null,
        public ?string $addressLine2 = null,
        public ?string $postalCode = null,
        public bool $isDefault = false,
    ) {}
}