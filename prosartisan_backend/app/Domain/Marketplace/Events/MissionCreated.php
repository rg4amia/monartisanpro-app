<?php

namespace App\Domain\Marketplace\Events;

use App\Domain\Identity\Models\ValueObjects\TradeCategory;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;
use App\Domain\Shared\ValueObjects\GPS_Coordinates;
use DateTime;

/**
 * Domain event fired when a new mission is created
 * Requirements: 11.1 - Notify nearby artisans when mission is created
 */
final class MissionCreated
{
 public function __construct(
  public readonly MissionId $missionId,
  public readonly UserId $clientId,
  public readonly GPS_Coordinates $location,
  public readonly TradeCategory $category,
  public readonly string $description,
  public readonly DateTime $occurredAt
 ) {}
}
