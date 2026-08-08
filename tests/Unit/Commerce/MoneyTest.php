<?php

namespace Tests\Unit\Commerce;

use InvalidArgumentException;
use Modules\Commerce\ValueObjects\Money;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_it_calculates_in_minor_units_without_float_rounding(): void
    {
        $unitPrice = Money::fromDecimal('4499.95', 'try');
        $total = $unitPrice->multiply(2)->subtract(Money::fromDecimal('0.10', 'TRY'));

        $this->assertSame('8999.80', $total->toDecimal());
        $this->assertSame('TRY', $total->currency);
    }

    public function test_it_allocates_every_minor_unit(): void
    {
        $allocation = Money::fromDecimal('10.00', 'TRY')->allocate([1, 1, 1]);

        $this->assertSame(['3.34', '3.33', '3.33'], array_map(
            fn (Money $money): string => $money->toDecimal(),
            $allocation,
        ));
    }

    public function test_it_rejects_invalid_decimal_values(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Money::fromDecimal(10.25, 'TRY');
    }
}