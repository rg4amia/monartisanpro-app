<?php

namespace App\Domain\Marketplace\Models\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object representing the type of a devis line item
 */
final class DevisLineType
{
 private const MATERIAL = 'MATERIAL';
 private const LABOR = 'LABOR';

 private const VALID_TYPES = [
  self::MATERIAL,
  self::LABOR,
 ];

 private const FRENCH_LABELS = [
  self::MATERIAL => 'Matériel',
  self::LABOR => 'Main-d\'œuvre',
 ];

 private string $value;

 private function __construct(string $value)
 {
  $value = strtoupper($value);

  if (!in_array($value, self::VALID_TYPES, true)) {
   throw new InvalidArgumentException("Invalid devis line type: {$value}");
  }

  $this->value = $value;
 }

 public static function material(): self
 {
  return new self(self::MATERIAL);
 }

 public static function labor(): self
 {
  return new self(self::LABOR);
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

 public function isMaterial(): bool
 {
  return $this->value === self::MATERIAL;
 }

 public function isLabor(): bool
 {
  return $this->value === self::LABOR;
 }

 public function equals(DevisLineType $other): bool
 {
  return $this->value === $other->value;
 }

 public function __toString(): string
 {
  return $this->value;
 }
}
