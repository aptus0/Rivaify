<?php

namespace Modules\Commerce\DTOs\Catalog;

use Modules\Commerce\Enums\Catalog\ProductStatus;
use Modules\Commerce\Enums\Catalog\ProductType;

readonly class ProductEditorData
{
    /**
     * @param  string[]  $tags
     * @param  ProductOptionInputData[]  $options
     * @param  ProductVariantEditorData[]  $variants
     */
    public function __construct(
        public string $title,
        public ?string $description = null,
        public ?string $slug = null,
        public ?int $categoryId = null,
        public ?int $brandId = null,
        public ProductType $productType = ProductType::Physical,
        public ProductStatus $status = ProductStatus::Draft,
        public ?string $vendor = null,
        public bool $isTaxable = true,
        public bool $requiresShipping = true,
        public ?string $metaTitle = null,
        public ?string $metaDescription = null,
        public ?string $packageWidth = null,
        public ?string $packageHeight = null,
        public ?string $packageLength = null,
        public string $packageDimensionUnit = 'cm',
        public array $tags = [],
        public array $options = [],
        public array $variants = [],
    ) {}
}