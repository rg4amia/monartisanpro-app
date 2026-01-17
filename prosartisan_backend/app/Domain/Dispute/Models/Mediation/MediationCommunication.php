<?php

namespace App\Domain\Dispute\Models\Mediation;

use App\Domain\Identity\Models\ValueObjects\UserId;
use DateTime;

/**
 * Value object representing a communication message in mediation
 */
final class MediationCommunication
{
 private string $message;
 private UserId $senderId;
 private DateTime $sentAt;

 public function __construct(string $message, UserId $senderId, DateTime $sentAt)
 {
  $this->message = $message;
  $this->senderId = $senderId;
  $this->sentAt = $sentAt;
 }

 public function getMessage(): string
 {
  return $this->message;
 }

 public function getSenderId(): UserId
 {
  return $this->senderId;
 }

 public function getSentAt(): DateTime
 {
  return $this->sentAt;
 }

 public function toArray(): array
 {
  return [
   'message' => $this->message,
   'sender_id' => $this->senderId->getValue(),
   'sent_at' => $this->sentAt->format('Y-m-d H:i:s'),
  ];
 }
}
