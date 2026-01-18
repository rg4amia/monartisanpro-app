<?php

namespace App\Domain\Worksite\Events;

use App\Domain\Worksite\Models\ValueObjects\JalonId;
use App\Domain\Worksite\Models\ValueObjects\ChantierId;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use DateTime;

/**
 * Domain event fired when a milestone is validated by client or auto-validated
 *
 * Requirements: 6.6 - Release labor payment when milestone is validated
 */
final class MilestoneValidated
{
 public function __construct(
  public readonly JalonId $jalonId,
  public readonly ChantierId $chantierId,
  public readonly UserId $clientId,
  public readonly UserId $artisanId,
  public readonly MoneyAmount $laborAmountToRelease,
  public readonly bool $wasAutoValidated,
  public readonly DateTime $occurredAt
 ) {}
}
