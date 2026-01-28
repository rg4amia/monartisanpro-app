<?php

namespace App\Domain\Reputation\Models\ValueObjects;

use Illuminate\Support\Str;

/**
 * Value object representing a Rating identifier
 */
final class RatingId
{
    private string $value;

    public function __construct(string $value)
    {
        if (empty($value)) {
            throw new \InvalidArgumentException('Rating ID cannot be empty');
        }
        $this->value = $value;
    }

    public static function generate(): self
    {
        return new self(Str::uuid()->toString());
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(RatingId $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
