<?php

namespace App\Domain\Identity\Models\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object representing an email address
 */
final class Email
{
    private string $value;

    public function __construct(string $value)
    {
        $value = trim(strtolower($value));

        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email address: {$value}");
        }

        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getDomain(): string
    {
        $parts = explode('@', $this->value);
        return $parts[1] ?? '';
    }

    public function getLocalPart(): string
    {
        $parts = explode('@', $this->value);
        return $parts[0] ?? '';
    }

    public function equals(Email $other): bool
    {
        return $this->value === $other->value;
    }

    public function toString(): string
    {
        return $this->getValue();
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
