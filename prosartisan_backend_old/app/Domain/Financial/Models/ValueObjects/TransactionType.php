<?php

namespace App\Domain\Financial\Models\ValueObjects;

use InvalidArgumentException;

/**
 * Value object representing Transaction type
 */
final class TransactionType
{
    public const ESCROW_BLOCK = 'ESCROW_BLOCK';

    public const MATERIAL_RELEASE = 'MATERIAL_RELEASE';

    public const LABOR_RELEASE = 'LABOR_RELEASE';

    public const REFUND = 'REFUND';

    public const JETON_VALIDATION = 'JETON_VALIDATION';

    public const SERVICE_FEE = 'SERVICE_FEE';

    private const VALID_TYPES = [
        self::ESCROW_BLOCK,
        self::MATERIAL_RELEASE,
        self::LABOR_RELEASE,
        self::REFUND,
        self::JETON_VALIDATION,
        self::SERVICE_FEE,
    ];

    private const FRENCH_LABELS = [
        self::ESCROW_BLOCK => 'Blocage séquestre',
        self::MATERIAL_RELEASE => 'Libération matériaux',
        self::LABOR_RELEASE => 'Libération main-d\'œuvre',
        self::REFUND => 'Remboursement',
        self::JETON_VALIDATION => 'Validation jeton',
        self::SERVICE_FEE => 'Frais de service',
    ];

    private string $value;

    public function __construct(string $value)
    {
        $this->validateType($value);
        $this->value = $value;
    }

    public static function escrowBlock(): self
    {
        return new self(self::ESCROW_BLOCK);
    }

    public static function materialRelease(): self
    {
        return new self(self::MATERIAL_RELEASE);
    }

    public static function laborRelease(): self
    {
        return new self(self::LABOR_RELEASE);
    }

    public static function refund(): self
    {
        return new self(self::REFUND);
    }

    public static function jetonValidation(): self
    {
        return new self(self::JETON_VALIDATION);
    }

    public static function serviceFee(): self
    {
        return new self(self::SERVICE_FEE);
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

    public function equals(TransactionType $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private function validateType(string $value): void
    {
        if (! in_array($value, self::VALID_TYPES, true)) {
            throw new InvalidArgumentException(
                "Invalid transaction type: {$value}. Valid types are: ".implode(', ', self::VALID_TYPES)
            );
        }
    }
}
