<?php

namespace App\Application\UseCases\Financial\GenerateJeton;

/**
 * Command to generate a jeton for materials purchase
 *
 * Requirements: 5.1
 */
final class GenerateJetonCommand
{
 public function __construct(
  public readonly string $sequestreId,
  public readonly string $artisanId,
  public readonly array $supplierIds = []
 ) {}
}
