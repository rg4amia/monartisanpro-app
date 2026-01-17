<?php

namespace App\Domain\Dispute\Models\ValueObjects;

use InvalidArgumentException;

/**
 * Value object representing dispute status
 */
final class DisputeStatus
{
    public const OPEN = 'OPEN';
    public const IN_MEDIATION = 'IN_MEDIATION';
    public const IN_ARBITRATION = 'IN_ARBITRATION';
    public const RESOLVED = 'RESOLVED';
    public const CLOSED = 'CLOSED';

    private const VALID_STATUSES = [
        self::OPEN,
        self::IN_MEDIATION,
        self::IN_ARBITRATION,
        self::RESOLVED,
        self::CLOSED,
    ];

    private const FRENCH_LABELS = [
        self::OPEN => 'Ouvert',
        self::IN_MEDIATION => 'En médiation',
        self::IN_ARBITRATION => 'En arbitrage',
        self::RESOLVED => 'Résolu',
        self::CLOSED => 'Fermé',
    ];

    private string $value;

    public function __construct(string $value)
    {
        $this->validateStatus($value);
        $this->value = $value;
    }

    public static function open(): self
    {
        return new self(self::OPEN);
    }

    public static function inMediation(): self
    {
        return new self(self::IN_MEDIATION);
    }

    public static function inArbitration(): self
    {
        return new self(self::IN_ARBITRATION);
    }

    public static function resolved(): self
    {
        return new self(self::RESOLVED);
    }

    public static function closed(): self
    {
        return new self(self::CLOSED);
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

    public function isOpen(): bool
    {
        return $this->value === self::OPEN;
    }

    public function isInMediation(): bool
    {
        return $this->value === self::IN_MEDIATION;
    }

    public function isInArbitration(): bool
    {
        return $this->value === self::IN_ARBITRATION;
    }

    public function isResolved(): bool
    {
        return $this->value === self::RESOLVED;
    }

    public function isClosed(): bool
    {
        return $this->value === self::CLOSED;
    }

    public function equals(DisputeStatus $other): bool
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
                "Invalid dispute status: {$value}. Valid statuses are: " . implode(', ', self::VALID_STATUSES)
            );
        }
    }
}
