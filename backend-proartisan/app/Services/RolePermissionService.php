<?php

namespace App\Services;

use App\Models\Permission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RolePermissionService
{
    /**
     * Liste toutes les permissions disponibles.
     */
    public function getAllPermissions(): Collection
    {
        return Permission::all();
    }

    /**
     * Liste les permissions associées à un rôle.
     */
    public function getRolePermissions(string $role): array
    {
        return DB::table('permission_role')
            ->join('permissions', 'permission_role.permission_id', '=', 'permissions.id')
            ->where('permission_role.role', $role)
            ->pluck('permissions.name')
            ->toArray();
    }

    /**
     * Assigne une permission à un rôle.
     */
    public function assignPermissionToRole(string $role, string $permissionName): void
    {
        $this->validateRole($role);

        $permission = Permission::where('name', $permissionName)->first();
        if (! $permission) {
            throw ValidationException::withMessages([
                'permission' => ["La permission '{$permissionName}' n'existe pas."],
            ]);
        }

        $exists = DB::table('permission_role')
            ->where('permission_id', $permission->id)
            ->where('role', $role)
            ->exists();

        if (! $exists) {
            DB::table('permission_role')->insert([
                'permission_id' => $permission->id,
                'role' => $role,
                'created_at' => now(),
            ]);
        }

        // Effacer le cache
        Cache::forget("role_permissions_{$role}");
    }

    /**
     * Révoque une permission d'un rôle.
     */
    public function revokePermissionFromRole(string $role, string $permissionName): void
    {
        $this->validateRole($role);

        $permission = Permission::where('name', $permissionName)->first();
        if (! $permission) {
            throw ValidationException::withMessages([
                'permission' => ["La permission '{$permissionName}' n'existe pas."],
            ]);
        }

        DB::table('permission_role')
            ->where('permission_id', $permission->id)
            ->where('role', $role)
            ->delete();

        // Effacer le cache
        Cache::forget("role_permissions_{$role}");
    }

    /**
     * Valide que le rôle fait partie des rôles autorisés.
     */
    private function validateRole(string $role): void
    {
        $validRoles = ['client', 'artisan', 'fournisseur', 'referent', 'livreur', 'driver', 'admin'];

        if (! in_array($role, $validRoles)) {
            throw ValidationException::withMessages([
                'role' => ["Le rôle '{$role}' n'est pas valide."],
            ]);
        }
    }
}
