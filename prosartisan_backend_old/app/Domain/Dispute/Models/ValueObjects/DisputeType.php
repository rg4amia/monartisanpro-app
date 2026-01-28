<?php

namespace App\Domain\Dispute\Models\ValueObjects;

use InvalidArgumentException;

/**
 * Value object representing dispute type
 */
final class DisputeType
{
    public const QUALITY = 'QUALITY';

    public const PAYMENT = 'PAYMENT';

    public const DELAY = 'DELAY';

    public const OTHER = 'OTHER';

    private const VALID_TYPES = [
        self::QUALITY,
        self::PAYMENT,
        self::DELAY,
        self::OTHER,
    ];

    private const FRENCH_LABELS = [
        self::QUALITY => 'Qualité',
        self::PAYMENT => 'Paiement',
        self::DELAY => 'Retard',
        self::OTHER => 'Autre',
    ];

    private string $value;

    public function __construct(string $value)
    {
        $this->validateType($value);
        $this->value = $value;
    }

    public static function quality(): self
    {
        return new self(self::QUALITY);
    }

    public static function payment(): self
    {
        return new self(self::PAYMENT);
    }

    public static function delay(): self
    {
        return new self(self::DELAY);
    }

    public static function other(): self
    {
        return new self(self::OTHER);
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

    public function isQuality(): bool
    {
        return $this->value === self::QUALITY;
    }

    public function isPayment(): bool
    {
        return $this->value === self::PAYMENT;
    }

    public function isDelay(): bool
    {
        return $this->value === self::DELAY;
    }

    public function isOther(): bool
    {
        return $this->value === self::OTHER;
    }

    public function equals(DisputeType $other): bool
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
                "Invalid dispute type: {$value}. Valid types are: ".implode(', ', self::VALID_TYPES)
            );
        }
    }
}
