<?php

namespace App\Domain\Marketplace\Models\ValueObjects;

use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Value object representing a Devis identifier
 */
final class DevisId
{
    private string $value;

    public function __construct(string $value)
    {
        $this->validateUuid($value);
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

    public function equals(DevisId $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private function validateUuid(string $value): void
    {
        if (! Str::isUuid($value)) {
            throw new InvalidArgumentException("Invalid UUID format: {$value}");
        }
    }
}
