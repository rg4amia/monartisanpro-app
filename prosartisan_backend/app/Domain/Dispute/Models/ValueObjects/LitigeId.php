<?php

namespace App\Domain\Dispute\Models\ValueObjects;

use InvalidArgumentException;
use Ramsey\Uuid\Uuid;

/**
 * Value object representing a Litige identifier
 */
final class LitigeId
{
    private string $value;

    public function __construct(string $value)
    {
        $this->validateUuid($value);
        $this->value = $value;
    }

    public static function generate(): self
    {
        return new self(Uuid::uuid4()->toString());
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(LitigeId $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private function validateUuid(string $value): void
    {
        if (! Uuid::isValid($value)) {
            throw new InvalidArgumentException("Invalid UUID format: {$value}");
        }
    }
}
