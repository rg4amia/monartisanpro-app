<?php

namespace App\Domain\Dispute\Models\Arbitrage;

use App\Domain\Dispute\Models\ValueObjects\ArbitrationDecision;
use App\Domain\Identity\Models\ValueObjects\UserId;
use DateTime;
use InvalidArgumentException;

/**
 * Entity representing arbitration process in a dispute
 *
 * Handles final decision rendering when mediation fails
 *
 * Requirements: 9.4, 9.6
 */
final class Arbitration
{
 private UserId $arbitratorId;
 private ArbitrationDecision $decision;
 private string $justification;
 private DateTime $renderedAt;

 public function __construct(
  UserId $arbitratorId,
  ArbitrationDecision $decision,
  string $justification,
  ?DateTime $renderedAt = null
 ) {
  $this->validateJustification($justification);

  $this->arbitratorId = $arbitratorId;
  $this->decision = $decision;
  $this->justification = $justification;
  $this->renderedAt = $renderedAt ?? new DateTime();
 }

 /**
  * Create arbitration with decision
  *
  * Requirement 9.6: Render arbitration decision
  */
 public static function renderDecision(
  UserId $arbitratorId,
  ArbitrationDecision $decision,
  string $justification
 ): self {
  return new self($arbitratorId, $decision, $justification);
 }

 // Getters
 public function getArbitratorId(): UserId
 {
  return $this->arbitratorId;
 }

 public function getDecision(): ArbitrationDecision
 {
  return $this->decision;
 }

 public function getJustification(): string
 {
  return $this->justification;
 }

 public function getRenderedAt(): DateTime
 {
  return $this->renderedAt;
 }

 public function toArray(): array
 {
  return [
   'arbitrator_id' => $this->arbitratorId->getValue(),
   'decision' => $this->decision->toArray(),
   'justification' => $this->justification,
   'rendered_at' => $this->renderedAt->format('Y-m-d H:i:s'),
  ];
 }

 private function validateJustification(string $justification): void
 {
  if (empty(trim($justification))) {
   throw new InvalidArgumentException('Arbitration justification cannot be empty');
  }

  if (strlen($justification) < 10) {
   throw new InvalidArgumentException('Arbitration justification must be at least 10 characters long');
  }
 }
}
