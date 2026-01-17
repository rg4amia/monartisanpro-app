<?php

namespace App\Domain\Dispute\Models\ValueObjects;

use App\Domain\Shared\ValueObjects\MoneyAmount;

/**
 * Value object representing an arbitration decision
 */
final class ArbitrationDecision
{
 private DecisionType $type;
 private ?MoneyAmount $amount;

 public function __construct(DecisionType $type, ?MoneyAmount $amount = null)
 {
  $this->type = $type;
  $this->amount = $amount;
 }

 public static function refundClient(MoneyAmount $amount): self
 {
  return new self(DecisionType::refundClient(), $amount);
 }

 public static function payArtisan(MoneyAmount $amount): self
 {
  return new self(DecisionType::payArtisan(), $amount);
 }

 public static function partialRefund(MoneyAmount $amount): self
 {
  return new self(DecisionType::partialRefund(), $amount);
 }

 public static function freezeFunds(): self
 {
  return new self(DecisionType::freezeFunds());
 }

 public function getType(): DecisionType
 {
  return $this->type;
 }

 public function getAmount(): ?MoneyAmount
 {
  return $this->amount;
 }

 public function hasAmount(): bool
 {
  return $this->amount !== null;
 }

 public function equals(ArbitrationDecision $other): bool
 {
  $amountEquals = ($this->amount === null && $other->amount === null) ||
   ($this->amount !== null && $other->amount !== null && $this->amount->equals($other->amount));

  return $this->type->equals($other->type) && $amountEquals;
 }

 public function toArray(): array
 {
  return [
   'type' => $this->type->getValue(),
   'type_label' => $this->type->getFrenchLabel(),
   'amount' => $this->amount?->toArray(),
  ];
 }
}
