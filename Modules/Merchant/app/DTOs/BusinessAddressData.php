<?php

namespace Modules\Merchant\DTOs;

readonly class BusinessAddressData
{
    public function __construct(
        public string $line1,
        public string $city,
        public ?string $line2 = null,
        public ?string $state = null,
        public ?string $postalCode = null,
        public string $countryCode = 'TR',
        public string $type = 'registered',
    ) {}
}
