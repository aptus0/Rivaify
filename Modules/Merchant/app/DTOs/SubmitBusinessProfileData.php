<?php

namespace Modules\Merchant\DTOs;

readonly class SubmitBusinessProfileData
{
    /**
     * @param  BusinessAddressData[]  $addresses
     */
    public function __construct(
        public string $legalName,
        public array $addresses,
        public ?string $tradeName = null,
        public ?string $registrationNumber = null,
        public ?string $contactEmail = null,
        public ?string $contactPhone = null,
    ) {}
}
