<?php

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;

/**
 * Value Object representing a currency
 * Currently supports only XOF (West African CFA franc)
 */
final class Currency
{
 private const XOF_CODE = 'XOF';
 private const XOF_SYMBOL = 'FCFA';

 private string $code;
 private string $symbol;

 private function __construct(string $code, string $symbol)
 {
  $this->code = $code;
  $this->symbol = $symbol;
 }

 public static function XOF(): self
 {
  return new self(self::XOF_CODE, self::XOF_SYMBOL);
 }

 public static function fromCode(string $code): self
 {
  if ($code !== self::XOF_CODE) {
   throw new InvalidArgumentException("Unsupported currency code: {$code}. Only XOF is supported.");
  }

  return self::XOF();
 }

 public function getCode(): string
 {
  return $this->code;
 }

 public function getSymbol(): string
 {
  return $this->symbol;
 }

 public function equals(Currency $other): bool
 {
  return $this->code === $other->code;
 }

 public function __toString(): string
 {
  return $this->code;
 }
}
