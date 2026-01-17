<?php

namespace App\Domain\Identity\Models\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object representing the type of user in the system
 */
final class UserType
{
    private const CLIENT = 'CLIENT';
    private const ARTISAN = 'ARTISAN';
    private const FOURNISSEUR = 'FOURNISSEUR';
    private const REFERENT_ZONE = 'REFERENT_ZONE';
    private const ADMIN = 'ADMIN';

    private const VALID_TYPES = [
        self::CLIENT,
        self::ARTISAN,
        self::FOURNISSEUR,
        self::REFERENT_ZONE,
        self::ADMIN,
    ];

    private string $value;

    private function __construct(string $value)
    {
        if (!in_array($value, self::VALID_TYPES, true)) {
            throw new InvalidArgumentException("Invalid user type: {$value}");
        }

        $this->value = $value;
    }

    public static function CLIENT(): self
    {
        return new self(self::CLIENT);
    }

    public static function ARTISAN(): self
    {
        return new self(self::ARTISAN);
    }

    public static function FOURNISSEUR(): self
    {
        return new self(self::FOURNISSEUR);
    }

    public static function REFERENT_ZONE(): self
    {
        return new self(self::REFERENT_ZONE);
    }

    public static function ADMIN(): self
    {
        return new self(self::ADMIN);
    }

    public static function fromString(string $value): self
    {
        return new self(strtoupper($value));
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function isClient(): bool
    {
        return $this->value === self::CLIENT;
    }

    public function isArtisan(): bool
    {
        return $this->value === self::ARTISAN;
    }

    public function isFournisseur(): bool
    {
        return $this->value === self::FOURNISSEUR;
    }

    public function isReferentZone(): bool
    {
        return $this->value === self::REFERENT_ZONE;
    }

    public function isAdmin(): bool
    {
        return $this->value === self::ADMIN;
    }

    public function equals(UserType $other): bool
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
