<?php

namespace App\Domain\Financial\Events;

use App\Domain\Financial\Models\ValueObjects\SequestreId;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use DateTime;

/**
 * Domain event fired when funds are blocked in escrow
 *
 * Requirement 4.1: Block funds in escrow after quote acceptance
 */
final class FundsBlocked
{
 public SequestreId $sequestreId;
 public MissionId $missionId;
 public MoneyAmount $totalAmount;
 public DateTime $occurredAt;

 public function __construct(
  SequestreId $sequestreId,
  MissionId $missionId,
  MoneyAmount $totalAmount,
  ?DateTime $occurredAt = null
 ) {
  $this->sequestreId = $sequestreId;
  $this->missionId = $missionId;
  $this->totalAmount = $totalAmount;
  $this->occurredAt = $occurredAt ?? new DateTime();
 }

 public function toArray(): array
 {
  return [
   'sequestre_id' => $this->sequestreId->getValue(),
   'mission_id' => $this->missionId->getValue(),
   'total_amount' => $this->totalAmount->toArray(),
   'occurred_at' => $this->occurredAt->format('Y-m-d H:i:s'),
  ];
 }
}
