<?php

namespace App\Domain\Marketplace\Models\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object representing the status of a devis (quote)
 */
final class DevisStatus
{
 private const PENDING = 'PENDING';
 private const ACCEPTED = 'ACCEPTED';
 private const REJECTED = 'REJECTED';

 private const VALID_STATUSES = [
  self::PENDING,
  self::ACCEPTED,
  self::REJECTED,
 ];

 private const FRENCH_LABELS = [
  self::PENDING => 'En attente',
  self::ACCEPTED => 'Accepté',
  self::REJECTED => 'Rejeté',
 ];

 private string $value;

 private function __construct(string $value)
 {
  $value = strtoupper($value);

  if (!in_array($value, self::VALID_STATUSES, true)) {
   throw new InvalidArgumentException("Invalid devis status: {$value}");
  }

  $this->value = $value;
 }

 public static function pending(): self
 {
  return new self(self::PENDING);
 }

 public static function accepted(): self
 {
  return new self(self::ACCEPTED);
 }

 public static function rejected(): self
 {
  return new self(self::REJECTED);
 }

 public static function fromString(string $value): self
 {
  return new self($value);
 }

 public function getValue(): string
 {
  return $this->value;
 }

 /**
  * Get French label for display
  */
 public function getLabel(): string
 {
  return self::FRENCH_LABELS[$this->value];
 }

 public function isPending(): bool
 {
  return $this->value === self::PENDING;
 }

 public function isAccepted(): bool
 {
  return $this->value === self::ACCEPTED;
 }

 public function isRejected(): bool
 {
  return $this->value === self::REJECTED;
 }

 public function equals(DevisStatus $other): bool
 {
  return $this->value === $other->value;
 }

 public function __toString(): string
 {
  return $this->value;
 }
}
