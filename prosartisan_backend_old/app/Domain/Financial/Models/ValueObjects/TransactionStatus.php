<?php

namespace App\Domain\Financial\Models\ValueObjects;

use InvalidArgumentException;

/**
 * Value object representing Transaction status
 */
final class TransactionStatus
{
    public const PENDING = 'PENDING';

    public const COMPLETED = 'COMPLETED';

    public const FAILED = 'FAILED';

    public const CANCELLED = 'CANCELLED';

    private const VALID_STATUSES = [
        self::PENDING,
        self::COMPLETED,
        self::FAILED,
        self::CANCELLED,
    ];

    private const FRENCH_LABELS = [
        self::PENDING => 'En attente',
        self::COMPLETED => 'Terminée',
        self::FAILED => 'Échouée',
        self::CANCELLED => 'Annulée',
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

    public static function completed(): self
    {
        return new self(self::COMPLETED);
    }

    public static function failed(): self
    {
        return new self(self::FAILED);
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

    public function isPending(): bool
    {
        return $this->value === self::PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->value === self::COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->value === self::FAILED;
    }

    public function isCancelled(): bool
    {
        return $this->value === self::CANCELLED;
    }

    public function equals(TransactionStatus $other): bool
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
                "Invalid transaction status: {$value}. Valid statuses are: ".implode(', ', self::VALID_STATUSES)
            );
        }
    }
}
