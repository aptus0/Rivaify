<?php

namespace Modules\Store\DTOs;

readonly class CreateStoreData
{
    public function __construct(
        public string $name,
        public string $defaultCurrency = 'TRY',
        public string $defaultLocale = 'tr',
        public string $timezone = 'Europe/Istanbul',
        public string $countryCode = 'TR',
    ) {}
}
