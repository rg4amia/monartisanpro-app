<?php

namespace App\Domain\Marketplace\Events;

use App\Domain\Marketplace\Models\ValueObjects\DevisId;
use App\Domain\Marketplace\Models\ValueObjects\MissionId;
use App\Domain\Identity\Models\ValueObjects\UserId;
use App\Domain\Shared\ValueObjects\MoneyAmount;
use DateTime;

/**
 * Domain event fired when a client accepts a quote
 *
 * Requirements: 3.7 - Initiate escrow process when quote is accepted
 */
final class QuoteAccepted
{
 public function __construct(
  public readonly DevisId $devisId,
  public readonly MissionId $missionId,
  public readonly UserId $clientId,
  public readonly UserId $artisanId,
  public readonly MoneyAmount $totalAmount,
  public readonly MoneyAmount $materialsAmount,
  public readonly MoneyAmount $laborAmount,
  public readonly DateTime $occurredAt
 ) {}
}
