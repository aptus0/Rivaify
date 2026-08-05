<?php

namespace Modules\Commerce\DTOs\Catalog;

use Modules\Commerce\Enums\Catalog\ProductType;

readonly class CreateProductData
{
    public function __construct(
        public string $title,
        public ?string $description = null,
        public ProductType $productType = ProductType::Physical,
        public ?string $vendor = null,
        public bool $isTaxable = true,
        public bool $requiresShipping = true,
    ) {}
}
