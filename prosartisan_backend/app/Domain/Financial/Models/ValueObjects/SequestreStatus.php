<?php

namespace App\Domain\Financial\Models\ValueObjects;

use InvalidArgumentException;

/**
 * Value object representing Sequestre status
 */
final class SequestreStatus
{
    public const BLOCKED = 'BLOCKED';
    public const PARTIAL = 'PARTIAL';
    public const RELEASED = 'RELEASED';
    public const REFUNDED = 'REFUNDED';

    private const VALID_STATUSES = [
        self::BLOCKED,
        self::PARTIAL,
        self::RELEASED,
        self::REFUNDED,
    ];

    private const FRENCH_LABELS = [
        self::BLOCKED => 'Bloqué',
        self::PARTIAL => 'Partiellement libéré',
        self::RELEASED => 'Libéré',
        self::REFUNDED => 'Remboursé',
    ];

    private string $value;

    public function __construct(string $value)
    {
        $this->validateStatus($value);
        $this->value = $value;
    }

    public static function blocked(): self
    {
        return new self(self::BLOCKED);
    }

    public static function partial(): self
    {
        return new self(self::PARTIAL);
    }

    public static function released(): self
    {
        return new self(self::RELEASED);
    }

    public static function refunded(): self
    {
        return new self(self::REFUNDED);
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

    public function isBlocked(): bool
    {
        return $this->value === self::BLOCKED;
    }

    public function isPartial(): bool
    {
        return $this->value === self::PARTIAL;
    }

    public function isReleased(): bool
    {
        return $this->value === self::RELEASED;
    }

    public function isRefunded(): bool
    {
        return $this->value === self::REFUNDED;
    }

    public function equals(SequestreStatus $other): bool
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
                "Invalid sequestre status: {$value}. Valid statuses are: " . implode(', ', self::VALID_STATUSES)
            );
        }
    }
}
