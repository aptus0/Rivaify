<?php

namespace Modules\Commerce\DTOs\Catalog;

use Modules\Commerce\Enums\Catalog\ProductStatus;

readonly class ProductVariantEditorData
{
    /**
     * @param  array<int, int>  $inventoryByLocationId
     */
    public function __construct(
        public string $title,
        public string $price,
        public ?string $compareAtPrice = null,
        public ?string $costPrice = null,
        public ?string $sku = null,
        public ?string $barcode = null,
        public ?string $weight = null,
        public string $weightUnit = 'kg',
        public bool $requiresShipping = true,
        public bool $isTaxable = true,
        public ProductStatus $status = ProductStatus::Draft,
        public bool $trackInventory = true,
        public bool $allowOversell = false,
        public array $inventoryByLocationId = [],
    ) {}
}