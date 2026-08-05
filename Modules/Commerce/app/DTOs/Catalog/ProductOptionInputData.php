<?php

namespace Modules\Commerce\DTOs\Catalog;

/**
 * One merchant-defined option ("Color") plus its ordered values
 * (["Black", "White"]) — the raw input to GenerateProductVariants, before
 * it becomes product_options/product_option_values rows.
 */
readonly class ProductOptionInputData
{
    /**
     * @param  string[]  $values
     */
    public function __construct(
        public string $name,
        public array $values,
    ) {}
}
