<?php

namespace Modules\Commerce\DTOs\Catalog;

readonly class CreateBrandData
{
    public function __construct(
        public string $name,
        public ?string $description = null,
    ) {}
}
