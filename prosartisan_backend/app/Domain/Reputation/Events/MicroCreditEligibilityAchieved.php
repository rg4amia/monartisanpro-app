<?php

namespace App\Domain\Reputation\Events;

use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Reputation\Models\ValueObjects\NZassaScore;
use DateTime;

/**
 * Domain event fired when an artisan achieves micro-credit eligibility (score > 70)
 */
class MicroCreditEligibilityAchieved
{
 public UserId $artisanId;
 public NZassaScore $score;
 public DateTime $occurredAt;

 public function __construct(UserId $artisanId, NZassaScore $score)
 {
  $this->artisanId = $artisanId;
  $this->score = $score;
  $this->occurredAt = new DateTime();
 }
}
