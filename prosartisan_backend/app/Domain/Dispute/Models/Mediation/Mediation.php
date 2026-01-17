<?php

namespace App\Domain\Dispute\Models\Mediation;

use App\Domain\Identity\Models\ValueObjects\UserId;
use DateTime;
use InvalidArgumentException;

/**
 * Entity representing mediation process in a dispute
 *
 * Handles communication between parties through a mediator
 *
 * Requirements: 9.3, 9.5
 */
final class Mediation
{
 private UserId $mediatorId;
 private array $communications; // Array of MediationCommunication
 private DateTime $startedAt;
 private ?DateTime $endedAt;

 public function __construct(
  UserId $mediatorId,
  ?array $communications = null,
  ?DateTime $startedAt = null,
  ?DateTime $endedAt = null
 ) {
  $this->mediatorId = $mediatorId;
  $this->communications = $communications ?? [];
  $this->startedAt = $startedAt ?? new DateTime();
  $this->endedAt = $endedAt;
 }

 /**
  * Add a communication message to the mediation
  *
  * Requirement 9.5: Provide communication channel during mediation
  */
 public function addCommunication(string $message, UserId $senderId): void
 {
  if (empty(trim($message))) {
   throw new InvalidArgumentException('Communication message cannot be empty');
  }

  $communication = new MediationCommunication(
   $message,
   $senderId,
   new DateTime()
  );

  $this->communications[] = $communication;
 }

 /**
  * End the mediation process
  */
 public function end(): void
 {
  if ($this->endedAt !== null) {
   throw new InvalidArgumentException('Mediation is already ended');
  }

  $this->endedAt = new DateTime();
 }

 /**
  * Check if mediation is active
  */
 public function isActive(): bool
 {
  return $this->endedAt === null;
 }

 /**
  * Get all communications
  */
 public function getCommunications(): array
 {
  return $this->communications;
 }

 /**
  * Get communications count
  */
 public function getCommunicationsCount(): int
 {
  return count($this->communications);
 }

 // Getters
 public function getMediatorId(): UserId
 {
  return $this->mediatorId;
 }

 public function getStartedAt(): DateTime
 {
  return $this->startedAt;
 }

 public function getEndedAt(): ?DateTime
 {
  return $this->endedAt;
 }

 public function toArray(): array
 {
  return [
   'mediator_id' => $this->mediatorId->getValue(),
   'communications_count' => $this->getCommunicationsCount(),
   'communications' => array_map(
    fn(MediationCommunication $comm) => $comm->toArray(),
    $this->communications
   ),
   'is_active' => $this->isActive(),
   'started_at' => $this->startedAt->format('Y-m-d H:i:s'),
   'ended_at' => $this->endedAt?->format('Y-m-d H:i:s'),
  ];
 }
}
