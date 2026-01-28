<?php

namespace App\Domain\Financial\Services;

use App\Domain\Financial\Models\JetonMateriel\JetonMateriel;
use App\Domain\Financial\Models\Sequestre\Sequestre;
use App\Domain\Identity\Models\Artisan\Artisan;

/**
 * Factory service for creating material tokens (jetons)
 *
 * Handles jeton creation with proper authorization and supplier assignment
 *
 * Requirement 5.1: Generate jeton with PA-XXXX codes
 */
interface JetonFactory
{
    /**
     * Create a jeton for materials purchase
     *
     * @param  array  $nearbySuppliers  Array of Fournisseur entities
     */
    public function createJeton(Sequestre $sequestre, Artisan $artisan, array $nearbySuppliers): JetonMateriel;

    /**
     * Create a jeton with specific authorized suppliers
     *
     * @param  array  $authorizedSupplierIds  Array of UserId
     */
    public function createJetonWithSuppliers(Sequestre $sequestre, Artisan $artisan, array $authorizedSupplierIds): JetonMateriel;
}
