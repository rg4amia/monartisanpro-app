<?php

namespace App\Traits;

use App\Services\RolePermissionService;
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
     * Droits d'origine du rôle quand la base n'en contient aucun (base non
     * seedée). Liste de référence : `RolePermissionService::DEFAULTS`.
     */
    private function getDefaultRolePermissions(string $role): array
    {
        return RolePermissionService::DEFAULTS[$role] ?? [];
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
