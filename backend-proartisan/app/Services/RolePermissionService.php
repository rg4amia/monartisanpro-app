<?php

namespace App\Services;

use App\Models\Permission;
use App\Services\Admin\AdminActivityLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RolePermissionService
{
    /**
     * Rôles de l'application dont les droits se règlent ici. `admin` n'y
     * figure pas : il a tous les droits par construction, et ses capacités
     * fines se règlent compte par compte. `driver` n'existe pas en base, le
     * rôle des livreurs est `livreur` (Règle d'or 63).
     */
    public const ROLES = ['client', 'artisan', 'fournisseur', 'referent', 'livreur'];

    /**
     * Actions qu'aucun retrait ne peut toucher : sans elles, l'espace du rôle
     * cesse de fonctionner pour tous ses utilisateurs, aussitôt.
     */
    public const PROTECTED = [
        'client' => [
            'mission.create', 'mission.view', 'devis.view', 'devis.accept', 'devis.refuse',
            'jalon.view', 'jalon.request-otp', 'jalon.validate-otp',
            'litige.create', 'kyc.upload', 'transactions.view',
        ],
        'artisan' => [
            'mission.view', 'devis.create', 'devis.view', 'jalon.view', 'jalon.submit',
            'jcode.create', 'litige.create', 'kyc.upload', 'transactions.view',
        ],
        'fournisseur' => [
            'jcode.scan', 'orders.view', 'orders.manage', 'kyc.upload',
            'supplier.dashboard', 'supplier-products.manage', 'transactions.view',
        ],
        'referent' => ['mission.view', 'mission.referent-validate', 'litige.view'],
        'livreur' => ['orders.view', 'deliveries.manage', 'kyc.upload', 'transactions.view'],
    ];

    /**
     * Actions réservées : elles ne s'attribuent qu'aux rôles listés. Une
     * liste vide réserve l'action aux administrateurs.
     */
    public const RESERVED = [
        'devis.accept' => ['client'],
        'devis.refuse' => ['client'],
        'jalon.validate-otp' => ['client'],
        'devis.create' => ['artisan'],
        'devis.update' => ['artisan'],
        'jalon.submit' => ['artisan'],
        'micro-credit.apply' => ['artisan'],
        'jcode.scan' => ['fournisseur'],
        'orders.manage' => ['fournisseur'],
        'supplier.dashboard' => ['fournisseur'],
        'supplier-products.manage' => ['fournisseur'],
        'deliveries.manage' => ['fournisseur', 'livreur'],
        'mission.referent-validate' => ['referent'],
        'litige.arbitrate' => ['referent'],
        'litige.vote' => ['referent', 'artisan'],
        'kyc.view' => [],
        'kyc.review' => [],
        'sms.send' => [],
        'sms.view' => [],
    ];

    public function __construct(private AdminActivityLogger $audit) {}

    public function isProtected(string $role, string $permission): bool
    {
        return in_array($permission, self::PROTECTED[$role] ?? [], true);
    }

    /** L'action peut-elle être attribuée à ce rôle ? */
    public function isAssignable(string $role, string $permission): bool
    {
        return ! array_key_exists($permission, self::RESERVED) || in_array($role, self::RESERVED[$permission], true);
    }

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
        $permission = $this->resolve($role, $permissionName);

        if (! $this->isAssignable($role, $permissionName)) {
            throw ValidationException::withMessages([
                'permission' => ["L'action « {$permissionName} » est réservée : elle ne s'attribue pas à ce rôle."],
            ]);
        }

        $exists = DB::table('permission_role')
            ->where('permission_id', $permission->id)
            ->where('role', $role)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('permission_role')->insert([
            'permission_id' => $permission->id,
            'role' => $role,
            'created_at' => now(),
        ]);

        Cache::forget("role_permissions_{$role}");

        $this->audit->log('role_permission.assigned', null, [
            'role' => $role,
            'permission' => $permissionName,
        ], subjectLabel: "{$role} · {$permissionName}");
    }

    /**
     * Révoque une permission d'un rôle.
     */
    public function revokePermissionFromRole(string $role, string $permissionName): void
    {
        $permission = $this->resolve($role, $permissionName);

        if ($this->isProtected($role, $permissionName)) {
            throw ValidationException::withMessages([
                'permission' => ["L'action « {$permissionName} » est indispensable à ce rôle : elle ne se retire pas."],
            ]);
        }

        $removed = DB::table('permission_role')
            ->where('permission_id', $permission->id)
            ->where('role', $role)
            ->delete();

        if ($removed === 0) {
            return;
        }

        Cache::forget("role_permissions_{$role}");

        $this->audit->log('role_permission.revoked', null, [
            'role' => $role,
            'permission' => $permissionName,
        ], subjectLabel: "{$role} · {$permissionName}");
    }

    /**
     * Contrôle le rôle et l'action. Les capacités `admin.*` du backoffice ne
     * s'attribuent jamais à un rôle de l'application.
     */
    private function resolve(string $role, string $permissionName): Permission
    {
        if (! in_array($role, self::ROLES, true)) {
            throw ValidationException::withMessages([
                'role' => ["Le rôle '{$role}' n'est pas valide."],
            ]);
        }

        $permission = Permission::where('name', $permissionName)->first();

        if (! $permission || str_starts_with($permissionName, 'admin.')) {
            throw ValidationException::withMessages([
                'permission' => ["La permission '{$permissionName}' n'existe pas."],
            ]);
        }

        return $permission;
    }
}
