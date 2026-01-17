<?php

namespace App\Domain\Financial\Events;

use App\Domain\Financial\Models\ValueObjects\JetonId;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use DateTime;

/**
 * Domain event fired when a jeton is generated
 *
 * Requirement 5.1: Generate jeton with PA-XXXX codes
 */
final class JetonGenerated
{
 public JetonId $jetonId;
 public UserId $artisanId;
 public string $code;
 public MoneyAmount $amount;
 public DateTime $expiresAt;
 public DateTime $occurredAt;

 public function __construct(
  JetonId $jetonId,
  UserId $artisanId,
  string $code,
  MoneyAmount $amount,
  DateTime $expiresAt,
  ?DateTime $occurredAt = null
 ) {
  $this->jetonId = $jetonId;
  $this->artisanId = $artisanId;
  $this->code = $code;
  $this->amount = $amount;
  $this->expiresAt = $expiresAt;
  $this->occurredAt = $occurredAt ?? new DateTime();
 }

 public function toArray(): array
 {
  return [
   'jeton_id' => $this->jetonId->getValue(),
   'artisan_id' => $this->artisanId->getValue(),
   'code' => $this->code,
   'amount' => $this->amount->toArray(),
   'expires_at' => $this->expiresAt->format('Y-m-d H:i:s'),
   'occurred_at' => $this->occurredAt->format('Y-m-d H:i:s'),
  ];
 }
}
