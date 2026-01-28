<?php

namespace App\Domain\Identity\Models\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object representing a phone number
 * Supports Côte d'Ivoire phone numbers (international format)
 */
final class PhoneNumber
{
    private string $value;

    public function __construct(string $value)
    {
        $value = $this->normalize($value);

        if (! $this->isValid($value)) {
            throw new InvalidArgumentException("Invalid phone number: {$value}");
        }

        $this->value = $value;
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /**
     * Normalize phone number to international format
     * Removes spaces, dashes, and ensures proper format
     */
    private function normalize(string $value): string
    {
        // Remove all non-digit characters except +
        $normalized = preg_replace('/[^\d+]/', '', $value);

        // If starts with 0, replace with +225 (Côte d'Ivoire country code)
        if (str_starts_with($normalized, '0')) {
            $normalized = '+225'.substr($normalized, 1);
        }

        // If doesn't start with +, add +225
        if (! str_starts_with($normalized, '+')) {
            $normalized = '+225'.$normalized;
        }

        return $normalized;
    }

    /**
     * Validate phone number format
     * Côte d'Ivoire numbers: +225 followed by 10 digits
     */
    private function isValid(string $value): bool
    {
        // Check for Côte d'Ivoire format: +225XXXXXXXXXX (10 digits after country code)
        if (preg_match('/^\+225\d{10}$/', $value)) {
            return true;
        }

        // Also accept other international formats (basic validation)
        if (preg_match('/^\+\d{10,15}$/', $value)) {
            return true;
        }

        return false;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Get formatted phone number for display
     * Example: +225 07 12 34 56 78
     */
    public function format(): string
    {
        if (str_starts_with($this->value, '+225')) {
            $number = substr($this->value, 4); // Remove +225

            return '+225 '.substr($number, 0, 2).' '.substr($number, 2, 2).' '.
                substr($number, 4, 2).' '.substr($number, 6, 2).' '.substr($number, 8, 2);
        }

        return $this->value;
    }

    /**
     * Get country code
     */
    public function getCountryCode(): string
    {
        if (preg_match('/^\+(\d{1,3})/', $this->value, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Check if this is a Côte d'Ivoire number
     */
    public function isCoteDIvoire(): bool
    {
        return str_starts_with($this->value, '+225');
    }

    public function equals(PhoneNumber $other): bool
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
