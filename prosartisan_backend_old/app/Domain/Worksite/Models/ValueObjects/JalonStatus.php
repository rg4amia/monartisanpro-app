<?php

namespace App\Domain\Worksite\Models\ValueObjects;

use InvalidArgumentException;

/**
 * Value object representing Jalon status
 */
final class JalonStatus
{
    public const PENDING = 'PENDING';

    public const SUBMITTED = 'SUBMITTED';

    public const VALIDATED = 'VALIDATED';

    public const CONTESTED = 'CONTESTED';

    private const VALID_STATUSES = [
        self::PENDING,
        self::SUBMITTED,
        self::VALIDATED,
        self::CONTESTED,
    ];

    private const FRENCH_LABELS = [
        self::PENDING => 'En attente',
        self::SUBMITTED => 'Soumis',
        self::VALIDATED => 'Validé',
        self::CONTESTED => 'Contesté',
    ];

    private string $value;

    public function __construct(string $value)
    {
        $this->validateStatus($value);
        $this->value = $value;
    }

    public static function pending(): self
    {
        return new self(self::PENDING);
    }

    public static function submitted(): self
    {
        return new self(self::SUBMITTED);
    }

    public static function validated(): self
    {
        return new self(self::VALIDATED);
    }

    public static function contested(): self
    {
        return new self(self::CONTESTED);
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

    public function isPending(): bool
    {
        return $this->value === self::PENDING;
    }

    public function isSubmitted(): bool
    {
        return $this->value === self::SUBMITTED;
    }

    public function isValidated(): bool
    {
        return $this->value === self::VALIDATED;
    }

    public function isContested(): bool
    {
        return $this->value === self::CONTESTED;
    }

    public function equals(JalonStatus $other): bool
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
                "Invalid jalon status: {$value}. Valid statuses are: ".implode(', ', self::VALID_STATUSES)
            );
        }
    }
}
