<?php

namespace App\Domain\Reputation\Models\ValueObjects;

use DateTime;

/**
 * Value object representing a historical score snapshot for audit trail
 */
final class ScoreSnapshot
{
 private NZassaScore $score;
 private string $reason;
 private DateTime $recordedAt;

 public function __construct(NZassaScore $score, string $reason, DateTime $recordedAt)
 {
  $this->score = $score;
  $this->reason = $reason;
  $this->recordedAt = $recordedAt;
 }

 public static function create(NZassaScore $score, string $reason): self
 {
  return new self($score, $reason, new DateTime());
 }

 public function getScore(): NZassaScore
 {
  return $this->score;
 }

 public function getReason(): string
 {
  return $this->reason;
 }

 public function getRecordedAt(): DateTime
 {
  return $this->recordedAt;
 }

 public function toArray(): array
 {
  return [
   'score' => $this->score->getValue(),
   'reason' => $this->reason,
   'recorded_at' => $this->recordedAt->format('Y-m-d H:i:s')
  ];
 }
}
