<?php

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object representing a monetary amount in XOF (West African CFA franc)
 * Stores amounts in centimes (smallest unit) to avoid floating-point precision issues
 */
final class MoneyAmount
{
    private int $amountInCentimes;

    private Currency $currency;

    public function __construct(int $amountInCentimes, ?Currency $currency = null)
    {
        if ($amountInCentimes < 0) {
            throw new InvalidArgumentException('Money amount cannot be negative');
        }

        $this->amountInCentimes = $amountInCentimes;
        $this->currency = $currency ?? Currency::XOF();
    }

    public static function fromCentimes(int $centimes): self
    {
        return new self($centimes);
    }

    public static function fromFrancs(float $francs): self
    {
        return new self((int) round($francs * 100));
    }

    public function add(MoneyAmount $other): self
    {
        $this->assertSameCurrency($other);

        return new self($this->amountInCentimes + $other->amountInCentimes, $this->currency);
    }

    public function subtract(MoneyAmount $other): self
    {
        $this->assertSameCurrency($other);

        if ($this->amountInCentimes < $other->amountInCentimes) {
            throw new InvalidArgumentException('Cannot subtract to negative amount');
        }

        return new self($this->amountInCentimes - $other->amountInCentimes, $this->currency);
    }

    public function multiply(float $factor): self
    {
        if ($factor < 0) {
            throw new InvalidArgumentException('Multiplication factor cannot be negative');
        }

        return new self((int) round($this->amountInCentimes * $factor), $this->currency);
    }

    public function percentage(int $percent): self
    {
        if ($percent < 0 || $percent > 100) {
            throw new InvalidArgumentException('Percentage must be between 0 and 100');
        }

        return new self((int) round($this->amountInCentimes * ($percent / 100)), $this->currency);
    }

    public function isGreaterThan(MoneyAmount $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amountInCentimes > $other->amountInCentimes;
    }

    public function isLessThan(MoneyAmount $other): bool
    {
        $this->assertSameCurrency($other);

        return $this->amountInCentimes < $other->amountInCentimes;
    }

    public function equals(MoneyAmount $other): bool
    {
        return $this->amountInCentimes === $other->amountInCentimes
            && $this->currency->equals($other->currency);
    }

    public function toFloat(): float
    {
        return $this->amountInCentimes / 100;
    }

    public function toCentimes(): int
    {
        return $this->amountInCentimes;
    }

    /**
     * Format amount according to French locale with thousand separators
     * Example: 1 000 000 FCFA
     */
    public function format(): string
    {
        $francs = $this->toFloat();
        $formatted = number_format($francs, 0, ',', ' ');

        return $formatted.' '.$this->currency->getSymbol();
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    private function assertSameCurrency(MoneyAmount $other): void
    {
        if (! $this->currency->equals($other->currency)) {
            throw new InvalidArgumentException('Cannot operate on different currencies');
        }
    }

    public function toArray(): array
    {
        return [
            'amount_centimes' => $this->amountInCentimes,
            'amount_francs' => $this->toFloat(),
            'currency' => $this->currency->getCode(),
            'formatted' => $this->format(),
        ];
    }
}
