<?php

namespace App\Application\UseCases\Financial\ValidateJeton;

/**
 * Command to validate a jeton for materials purchase
 *
 * Requirements: 5.3
 */
final class ValidateJetonCommand
{
 public function __construct(
  public readonly string $jetonCode,
  public readonly string $fournisseurId,
  public readonly int $amountCentimes,
  public readonly float $artisanLatitude,
  public readonly float $artisanLongitude,
  public readonly float $supplierLatitude,
  public readonly float $supplierLongitude
 ) {}
}
