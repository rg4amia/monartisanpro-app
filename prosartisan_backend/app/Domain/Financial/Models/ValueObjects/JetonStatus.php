<?php

namespace App\Domain\Financial\Models\ValueObjects;

use InvalidArgumentException;

/**
 * Value object representing Jeton status
 */
final class JetonStatus
{
    public const ACTIVE = 'ACTIVE';
    public const PARTIALLY_USED = 'PARTIALLY_USED';
    public const FULLY_USED = 'FULLY_USED';
    public const EXPIRED = 'EXPIRED';

    private const VALID_STATUSES = [
        self::ACTIVE,
        self::PARTIALLY_USED,
        self::FULLY_USED,
        self::EXPIRED,
    ];

    private const FRENCH_LABELS = [
        self::ACTIVE => 'Actif',
        self::PARTIALLY_USED => 'Partiellement utilisé',
        self::FULLY_USED => 'Entièrement utilisé',
        self::EXPIRED => 'Expiré',
    ];

    private string $value;

    public function __construct(string $value)
    {
        $this->validateStatus($value);
        $this->value = $value;
    }

    public static function active(): self
    {
        return new self(self::ACTIVE);
    }

    public static function partiallyUsed(): self
    {
        return new self(self::PARTIALLY_USED);
    }

    public static function fullyUsed(): self
    {
        return new self(self::FULLY_USED);
    }

    public static function expired(): self
    {
        return new self(self::EXPIRED);
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

    public function isActive(): bool
    {
        return $this->value === self::ACTIVE;
    }

    public function isPartiallyUsed(): bool
    {
        return $this->value === self::PARTIALLY_USED;
    }

    public function isFullyUsed(): bool
    {
        return $this->value === self::FULLY_USED;
    }

    public function isExpired(): bool
    {
        return $this->value === self::EXPIRED;
    }

    public function equals(JetonStatus $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private function validateStatus(string $value): void
    {
        if (!in_array($value, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException(
                "Invalid jeton status: {$value}. Valid statuses are: " . implode(', ', self::VALID_STATUSES)
            );
        }
    }
}
