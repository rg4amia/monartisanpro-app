<?php

namespace App\Domain\Financial\Services;

use App\Domain\Financial\Models\JetonMateriel\JetonMateriel;
use App\Domain\Financial\Models\Sequestre\Sequestre;
use App\Domain\Identity\Models\Artisan\Artisan;
use App\Domain\Identity\Models\ValueObjects\UserId;

/**
 * Default implementation of jeton factory
 */
final class DefaultJetonFactory implements JetonFactory
{
 public function createJeton(Sequestre $sequestre, Artisan $artisan, array $nearbySuppliers): JetonMateriel
 {
  // Extract supplier IDs from nearby suppliers
  $supplierIds = array_map(function ($supplier) {
   return $supplier->getId();
  }, $nearbySuppliers);

  return $this->createJetonWithSuppliers($sequestre, $artisan, $supplierIds);
 }

 public function createJetonWithSuppliers(Sequestre $sequestre, Artisan $artisan, array $authorizedSupplierIds): JetonMateriel
 {
  // Validate supplier IDs
  foreach ($authorizedSupplierIds as $supplierId) {
   if (!$supplierId instanceof UserId) {
    throw new \InvalidArgumentException('All supplier IDs must be UserId instances');
   }
  }

  return JetonMateriel::create(
   $sequestre->getId(),
   $artisan->getId(),
   $sequestre->getRemainingMaterials(),
   $authorizedSupplierIds
  );
 }
}
