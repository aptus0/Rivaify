<?php

namespace Modules\Commerce\ValueObjects;

use InvalidArgumentException;

final readonly class Money
{
    private const SCALE = 2;

    private function __construct(
        public int $amount,
        public string $currency,
    ) {}

    public static function fromMinor(int $amount, string $currency): self
    {
        return new self($amount, self::normalizeCurrency($currency));
    }

    public static function fromDecimal(mixed $amount, string $currency): self
    {
        if (! is_string($amount) && ! is_int($amount)) {
            throw new InvalidArgumentException('Money decimal amounts must be strings or integers.');
        }

        $amount = (string) $amount;
        if (preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $amount, $matches) !== 1) {
            throw new InvalidArgumentException("Invalid decimal money amount [{$amount}].");
        }

        $minor = ((int) $matches[2] * (10 ** self::SCALE))
            + (int) str_pad($matches[3] ?? '', self::SCALE, '0');

        return self::fromMinor($matches[1] === '-' ? -$minor : $minor, $currency);
    }

    public static function zero(string $currency): self
    {
        return self::fromMinor(0, $currency);
    }

    public function add(self $other): self
    {
        $this->assertSameCurrency($other);

        return self::fromMinor($this->amount + $other->amount, $this->currency);
    }

    public function subtract(self $other): self
    {
        $this->assertSameCurrency($other);

        return self::fromMinor($this->amount - $other->amount, $this->currency);
    }

    public function multiply(int $multiplier): self
    {
        if ($multiplier < 0) {
            throw new InvalidArgumentException('Money multipliers cannot be negative.');
        }

        return self::fromMinor($this->amount * $multiplier, $this->currency);
    }

    public function isLessThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount < $other->amount;
    }

    public function isGreaterThan(self $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amount > $other->amount;
    }

    public function percentage(string|int $percentage): self
    {
        $percentage = (string) $percentage;
        if (preg_match('/^(\d+)(?:\.(\d{1,2}))?$/', $percentage, $matches) !== 1) {
            throw new InvalidArgumentException("Invalid percentage [{$percentage}].");
        }

        $basisPoints = ((int) $matches[1] * 100) + (int) str_pad($matches[2] ?? '', 2, '0');
        if ($basisPoints > 10_000) {
            throw new InvalidArgumentException('Percentages cannot exceed 100.00.');
        }

        return $this->multiplyRatio($basisPoints, 10_000);
    }

    public function multiplyRatio(int $numerator, int $denominator): self
    {
        if ($numerator < 0 || $denominator < 1) {
            throw new InvalidArgumentException('Money ratios must be non-negative with a positive denominator.');
        }

        $absoluteAmount = abs($this->amount);
        $roundedAmount = intdiv(($absoluteAmount * $numerator) + intdiv($denominator, 2), $denominator);

        return self::fromMinor($this->amount < 0 ? -$roundedAmount : $roundedAmount, $this->currency);
    }

    /**
     * @param  array<int, int>  $ratios
     * @return array<int, self>
     */
    public function allocate(array $ratios): array
    {
        if ($ratios === [] || array_filter($ratios, fn (mixed $ratio): bool => ! is_int($ratio) || $ratio <= 0) !== []) {
            throw new InvalidArgumentException('Money allocation ratios must be positive integers.');
        }

        $totalRatio = array_sum($ratios);
        $remaining = abs($this->amount);
        $sign = $this->amount < 0 ? -1 : 1;
        $allocated = [];

        foreach ($ratios as $ratio) {
            $share = intdiv(abs($this->amount) * $ratio, $totalRatio);
            $allocated[] = $share;
            $remaining -= $share;
        }

        foreach ($allocated as $index => $share) {
            $allocated[$index] = self::fromMinor($sign * ($share + ($remaining > 0 ? 1 : 0)), $this->currency);
            $remaining--;
        }

        return $allocated;
    }

    public function toDecimal(): string
    {
        $absoluteAmount = abs($this->amount);
        $major = intdiv($absoluteAmount, 10 ** self::SCALE);
        $minor = str_pad((string) ($absoluteAmount % (10 ** self::SCALE)), self::SCALE, '0', STR_PAD_LEFT);

        return ($this->amount < 0 ? '-' : '').$major.'.'.$minor;
    }

    private static function normalizeCurrency(string $currency): string
    {
        $currency = strtoupper($currency);
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1) {
            throw new InvalidArgumentException("Invalid ISO currency code [{$currency}].");
        }

        return $currency;
    }

    private function assertSameCurrency(self $other): void
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Money operations require matching currencies.');
        }
    }
}