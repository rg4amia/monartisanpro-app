<?php

namespace App\Domain\Reputation\Models\ValueObjects;

use InvalidArgumentException;

/**
 * Value object representing a rating value (1-5 stars)
 */
final class RatingValue
{
    private int $value;

    public function __construct(int $value)
    {
        if ($value < 1 || $value > 5) {
            throw new InvalidArgumentException("Rating value must be between 1 and 5 stars, got: {$value}");
        }
        $this->value = $value;
    }

    public static function fromInt(int $value): self
    {
        return new self($value);
    }

    public static function oneStar(): self
    {
        return new self(1);
    }

    public static function twoStars(): self
    {
        return new self(2);
    }

    public static function threeStars(): self
    {
        return new self(3);
    }

    public static function fourStars(): self
    {
        return new self(4);
    }

    public static function fiveStars(): self
    {
        return new self(5);
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function equals(RatingValue $other): bool
    {
        return $this->value === $other->value;
    }

    public function isGreaterThan(RatingValue $other): bool
    {
        return $this->value > $other->value;
    }

    public function isLessThan(RatingValue $other): bool
    {
        return $this->value < $other->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }
}
