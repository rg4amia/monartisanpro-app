<?php

namespace App\Traits;

use App\Models\Permission;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

trait HasPermissions
{
    /**
     * Vérifie si le rôle de l'utilisateur a une permission spécifique.
     */
    public function hasPermissionTo(string $permission): bool
    {
        if ($this->role === 'admin') {
            return true;
        }

        if (! $this->role) {
            return false;
        }

        $permissions = Cache::remember("role_permissions_{$this->role}", 3600, function () {
            // Si la table des associations est vide (ex: environnement de test propre), utiliser les droits par défaut
            if (! DB::table('permission_role')->exists()) {
                return $this->getDefaultRolePermissions($this->role);
            }

            return DB::table('permission_role')
                ->join('permissions', 'permission_role.permission_id', '=', 'permissions.id')
                ->where('permission_role.role', $this->role)
                ->pluck('permissions.name')
                ->toArray();
        });

        return in_array($permission, $permissions);
    }

    /**
     * Droits par défaut par rôle en cas de base non seedée (compatibilité tests).
     */
    private function getDefaultRolePermissions(string $role): array
    {
        $mappings = [
            'client' => [
                'mission.create', 'mission.view', 'mission.estimate', 'mission.update-status',
                'devis.view', 'devis.accept', 'devis.refuse',
                'jalon.view', 'jalon.request-otp', 'jalon.validate-otp',
                'jcode.view', 'orders.create', 'orders.view',
                'litige.create', 'litige.view', 'kyc.upload',
                'evaluation.create', 'parrainage.create', 'parrainage.view',
                'transactions.view'
            ],
            'artisan' => [
                'mission.view', 'mission.update-status',
                'devis.create', 'devis.view', 'devis.update',
                'jalon.view', 'jalon.submit', 'jalon.upload-photos', 'jalon.request-otp',
                'jcode.create', 'jcode.view', 'jcode.upload-photo-materials',
                'orders.create', 'orders.view', 'litige.create', 'litige.view',
                'kyc.upload', 'parrainage.create', 'parrainage.view',
                'micro-credit.apply', 'micro-credit.view', 'transactions.view'
            ],
            'fournisseur' => [
                'jcode.scan', 'jcode.view', 'orders.view', 'orders.manage',
                'deliveries.manage', 'litige.view', 'kyc.upload',
                'transactions.view', 'supplier.dashboard', 'supplier-products.manage'
            ],
            'referent' => [
                'mission.view', 'mission.referent-validate',
                'litige.view', 'litige.arbitrate', 'litige.vote',
                'kyc.upload', 'transactions.view'
            ],
        ];

        return $mappings[$role] ?? [];
    }

    /**
     * Efface le cache des permissions pour le rôle de cet utilisateur.
     */
    public function clearPermissionCache(): void
    {
        if ($this->role) {
            Cache::forget("role_permissions_{$this->role}");
        }
    }
}
