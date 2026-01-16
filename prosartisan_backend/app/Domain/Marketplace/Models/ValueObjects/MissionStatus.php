<?php

namespace App\Domain\Marketplace\Models\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object representing the status of a mission
 */
final class MissionStatus
{
 private const OPEN = 'OPEN';
 private const QUOTED = 'QUOTED';
 private const ACCEPTED = 'ACCEPTED';
 private const CANCELLED = 'CANCELLED';

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

 private function __construct(string $value)
 {
  $value = strtoupper($value);

  if (!in_array($value, self::VALID_STATUSES, true)) {
   throw new InvalidArgumentException("Invalid mission status: {$value}");
  }

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

 /**
  * Get French label for display
  */
 public function getLabel(): string
 {
  return self::FRENCH_LABELS[$this->value];
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
}
