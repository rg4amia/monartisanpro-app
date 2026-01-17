<?php

namespace App\Domain\Financial\Models\ValueObjects;

use InvalidArgumentException;

/**
 * Value object representing a Jeton code in format PA-XXXX
 *
 * Requirement 5.1: Generate unique code in format PA-XXXX
 */
final class JetonCode
{
    private const PREFIX = 'PA-';
    private const CODE_LENGTH = 4;
    private const PATTERN = '/^PA-[0-9]{4}$/';

    private string $value;

    public function __construct(string $value)
    {
        $this->validateFormat($value);
        $this->value = $value;
    }

    /**
     * Generate a new jeton code with format PA-XXXX
     */
    public static function generate(): self
    {
        $code = self::PREFIX . str_pad(
            (string) random_int(0, 9999),
            self::CODE_LENGTH,
            '0',
            STR_PAD_LEFT
        );

        return new self($code);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(JetonCode $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private function validateFormat(string $value): void
    {
        if (!preg_match(self::PATTERN, $value)) {
            throw new InvalidArgumentException(
                "Invalid jeton code format: {$value}. Expected format: PA-XXXX"
            );
        }
    }
}
