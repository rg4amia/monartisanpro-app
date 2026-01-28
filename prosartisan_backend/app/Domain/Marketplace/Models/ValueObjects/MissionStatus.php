<?php

namespace App\Domain\Marketplace\Models\ValueObjects;

use InvalidArgumentException;

/**
 * Value object representing Mission status
 */
final class MissionStatus
{
    public const OPEN = 'OPEN';

    public const QUOTED = 'QUOTED';

    public const ACCEPTED = 'ACCEPTED';

    public const CANCELLED = 'CANCELLED';

    private const VALID_STATUSES = [
        self::OPEN,
        self::QUOTED,
        self::ACCEPTED,
        self::CANCELLED,
    ];

    private const FRENCH_LABELS = [
        self::OPEN => 'Ouverte',
        self::QUOTED => 'Devis reçus',
        self::ACCEPTED => 'Acceptée',
        self::CANCELLED => 'Annulée',
    ];

    private string $value;

    public function __construct(string $value)
    {
        $value = strtoupper($value);
        $this->validateStatus($value);
        $this->value = $value;
    }

    public static function open(): self
    {
        return new self(self::OPEN);
    }

    public static function quoted(): self
    {
        return new self(self::QUOTED);
    }

    public static function accepted(): self
    {
        return new self(self::ACCEPTED);
    }

    public static function cancelled(): self
    {
        return new self(self::CANCELLED);
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

    public function getLabel(): string
    {
        return $this->getFrenchLabel();
    }

    public function isOpen(): bool
    {
        return $this->value === self::OPEN;
    }

    public function isQuoted(): bool
    {
        return $this->value === self::QUOTED;
    }

    public function isAccepted(): bool
    {
        return $this->value === self::ACCEPTED;
    }

    public function isCancelled(): bool
    {
        return $this->value === self::CANCELLED;
    }

    public function equals(MissionStatus $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private function validateStatus(string $value): void
    {
        if (! in_array($value, self::VALID_STATUSES, true)) {
            throw new InvalidArgumentException(
                "Invalid mission status: {$value}. Valid statuses are: ".implode(', ', self::VALID_STATUSES)
            );
        }
    }
}
