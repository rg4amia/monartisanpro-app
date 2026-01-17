<?php

namespace App\Domain\Marketplace\Events;

use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Marketplace\Models\ValueObjects\DevisId;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use DateTime;

/**
 * Domain event fired when an artisan submits a quote
 * Requirements: 11.2 - Notify client when quote is submitted
 */
final class QuoteSubmitted
{
 public function __construct(
  public readonly DevisId $devisId,
  public readonly MissionId $missionId,
  public readonly UserId $artisanId,
  public readonly UserId $clientId,
  public readonly MoneyAmount $totalAmount,
  public readonly DateTime $occurredAt
 ) {}
}
