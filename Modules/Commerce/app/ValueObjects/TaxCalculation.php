<?php

namespace Modules\Commerce\ValueObjects;

use Modules\Commerce\Models\Tax\TaxRate;

final readonly class TaxCalculation
{
    /**
     * @param  array<int, Money>  $itemTaxes
     */
    public function __construct(
        public ?TaxRate $rate,
        public array $itemTaxes,
        public Money $total,
    ) {}

    public function isInclusive(): bool
    {
        return $this->rate?->is_inclusive ?? false;
    }
}