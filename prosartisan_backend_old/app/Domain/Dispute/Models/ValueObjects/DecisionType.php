<?php

namespace App\Domain\Dispute\Models\ValueObjects;

use InvalidArgumentException;

/**
 * Value object representing arbitration decision type
 */
final class DecisionType
{
    public const REFUND_CLIENT = 'REFUND_CLIENT';

    public const PAY_ARTISAN = 'PAY_ARTISAN';

    public const PARTIAL_REFUND = 'PARTIAL_REFUND';

    public const FREEZE_FUNDS = 'FREEZE_FUNDS';

    private const VALID_TYPES = [
        self::REFUND_CLIENT,
        self::PAY_ARTISAN,
        self::PARTIAL_REFUND,
        self::FREEZE_FUNDS,
    ];

    private const FRENCH_LABELS = [
        self::REFUND_CLIENT => 'Remboursement client',
        self::PAY_ARTISAN => 'Paiement artisan',
        self::PARTIAL_REFUND => 'Remboursement partiel',
        self::FREEZE_FUNDS => 'Gel des fonds',
    ];

    private string $value;

    public function __construct(string $value)
    {
        $this->validateType($value);
        $this->value = $value;
    }

    public static function refundClient(): self
    {
        return new self(self::REFUND_CLIENT);
    }

    public static function payArtisan(): self
    {
        return new self(self::PAY_ARTISAN);
    }

    public static function partialRefund(): self
    {
        return new self(self::PARTIAL_REFUND);
    }

    public static function freezeFunds(): self
    {
        return new self(self::FREEZE_FUNDS);
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

    public function isRefundClient(): bool
    {
        return $this->value === self::REFUND_CLIENT;
    }

    public function isPayArtisan(): bool
    {
        return $this->value === self::PAY_ARTISAN;
    }

    public function isPartialRefund(): bool
    {
        return $this->value === self::PARTIAL_REFUND;
    }

    public function isFreezeFunds(): bool
    {
        return $this->value === self::FREEZE_FUNDS;
    }

    public function equals(DecisionType $other): bool
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
                "Invalid decision type: {$value}. Valid types are: ".implode(', ', self::VALID_TYPES)
            );
        }
    }
}
