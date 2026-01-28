<?php

namespace App\Domain\Reputation\Models\ValueObjects;

use InvalidArgumentException;

/**
 * Value object representing the N'Zassa reputation score (0-100)
 */
final class NZassaScore
{
    private int $value;

    public function __construct(int $value)
    {
        if ($value < 0 || $value > 100) {
            throw new InvalidArgumentException("N'Zassa score must be between 0 and 100, got: {$value}");
        }
        $this->value = $value;
    }

    public static function fromInt(int $value): self
    {
        return new self($value);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    public static function maximum(): self
    {
        return new self(100);
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function isEligibleForCredit(): bool
    {
        return $this->value > 70; // Score > 700 (out of 1000) = 70 (out of 100)
    }

    public function equals(NZassaScore $other): bool
    {
        return $this->value === $other->value;
    }

    public function isGreaterThan(NZassaScore $other): bool
    {
        return $this->value > $other->value;
    }

    public function isLessThan(NZassaScore $other): bool
    {
        return $this->value < $other->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
