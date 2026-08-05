<?php

namespace Modules\Merchant\DTOs;

readonly class SubmitTaxProfileData
{
    public function __construct(
        public string $taxNumber,
        public string $legalEntityName,
        public ?string $taxOffice = null,
    ) {}
}
