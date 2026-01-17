<?php

namespace App\Application\UseCases\Financial\BlockEscrowFunds;

/**
 * Command to block funds in escrow after quote acceptance
 *
 * Requirements: 4.1, 4.2
 */
final class BlockEscrowFundsCommand
{
 public function __construct(
  public readonly string $missionId,
  public readonly string $devisId,
  public readonly string $clientId,
  public readonly string $artisanId,
  public readonly int $totalAmountCentimes
 ) {}
}
