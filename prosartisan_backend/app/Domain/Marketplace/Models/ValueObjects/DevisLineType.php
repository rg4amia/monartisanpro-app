<?php

namespace App\Domain\Marketplace\Models\ValueObjects;

use InvalidArgumentException;

/**
 * Value object representing Devis line item type
 */
final class DevisLineType
{
    public const MATERIAL = 'MATERIAL';
    public const LABOR = 'LABOR';

    private const VALID_TYPES = [
        self::MATERIAL,
        self::LABOR,
    ];

    private const FRENCH_LABELS = [
        self::MATERIAL => 'Matériel',
        self::LABOR => 'Main-d\'œuvre',
    ];

    private string $value;

    public function __construct(string $value)
    {
        $this->validateType($value);
        $this->value = $value;
    }

    public static function material(): self
    {
        return new self(self::MATERIAL);
    }

    public static function labor(): self
    {
        return new self(self::LABOR);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function getFrenchLabel(): string
    {
        return self::FRENCH_LABELS[$this->value];
    }

    public function isMaterial(): bool
    {
        return $this->value === self::MATERIAL;
    }

    public function isLabor(): bool
    {
        return $this->value === self::LABOR;
    }

    public function equals(DevisLineType $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private function validateType(string $value): void
    {
        if (!in_array($value, self::VALID_TYPES, true)) {
            throw new InvalidArgumentException(
                "Invalid devis line type: {$value}. Valid types are: " . implode(', ', self::VALID_TYPES)
            );
        }
    }
}
