<?php

namespace App\Domain\Identity\Models\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object representing an artisan's trade category
 */
final class TradeCategory
{
    private const PLUMBER = 'PLUMBER';
    private const ELECTRICIAN = 'ELECTRICIAN';
    private const MASON = 'MASON';

    private const VALID_CATEGORIES = [
        self::PLUMBER,
        self::ELECTRICIAN,
        self::MASON,
    ];

    private const FRENCH_LABELS = [
        self::PLUMBER => 'Plombier',
        self::ELECTRICIAN => 'Électricien',
        self::MASON => 'Maçon',
    ];

    private string $value;

    private function __construct(string $value)
    {
        $value = strtoupper($value);

        if (!in_array($value, self::VALID_CATEGORIES, true)) {
            throw new InvalidArgumentException("Invalid trade category: {$value}");
        }

        $this->value = $value;
    }

    public static function PLUMBER(): self
    {
        return new self(self::PLUMBER);
    }

    public static function ELECTRICIAN(): self
    {
        return new self(self::ELECTRICIAN);
    }

    public static function MASON(): self
    {
        return new self(self::MASON);
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Get French label for display
     */
    public function getLabel(): string
    {
        return self::FRENCH_LABELS[$this->value];
    }

    public function isPlumber(): bool
    {
        return $this->value === self::PLUMBER;
    }

    public function isElectrician(): bool
    {
        return $this->value === self::ELECTRICIAN;
    }

    public function isMason(): bool
    {
        return $this->value === self::MASON;
    }

    public function equals(TradeCategory $other): bool
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
