<?php

namespace App\Services;

use App\Models\Permission;
use App\Services\Admin\AdminActivityLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

/**
 * Droits des rôles de l'application (client, artisan, fournisseur, Référent,
 * livreur).
 *
 * Seules les actions **exigées par une route** (`can:<action>`) se règlent :
 * une action que rien n'exige n'a aucun effet, la proposer ferait croire à
 * l'administrateur qu'il coupe une fonction. La liste se lit dans la table
 * des routes ({@see self::effectiveActions()}) : brancher une action sur une
 * route la fait apparaître dans l'écran, sans autre changement.
 */
class RolePermissionService
{
    /**
     * Rôles de l'application dont les droits se règlent ici. `admin` n'y
     * figure pas : ses capacités fines se règlent compte par compte. `driver`
     * n'existe pas en base, le rôle des livreurs est `livreur` (Règle d'or 63).
     */
    public const ROLES = ['client', 'artisan', 'fournisseur', 'referent', 'livreur'];

    /**
     * Droits d'origine de chaque rôle — seule liste de référence : le seeder,
     * le repli de `HasPermissions` et le retour aux droits d'origine la lisent.
     */
    public const DEFAULTS = [
        'client' => [
            'mission.create', 'mission.view', 'mission.estimate', 'mission.update-status',
            'devis.view', 'devis.accept', 'devis.refuse',
            'jalon.view', 'jalon.request-otp', 'jalon.validate-otp',
            'jcode.view', 'orders.create', 'orders.view',
            'litige.create', 'litige.view', 'kyc.upload',
            'evaluation.create', 'parrainage.create', 'parrainage.view',
            'transactions.view',
        ],
        'artisan' => [
            'mission.view', 'mission.update-status',
            'devis.create', 'devis.view', 'devis.update',
            'jalon.view', 'jalon.submit', 'jalon.upload-photos', 'jalon.request-otp',
            'jcode.create', 'jcode.view', 'jcode.upload-photo-materials',
            'orders.create', 'orders.view', 'litige.create', 'litige.view',
            'kyc.upload', 'parrainage.create', 'parrainage.view',
            'micro-credit.apply', 'micro-credit.view', 'transactions.view',
        ],
        'fournisseur' => [
            'jcode.scan', 'jcode.view', 'orders.view', 'orders.manage',
            'deliveries.manage', 'litige.view', 'kyc.upload',
            'transactions.view', 'supplier.dashboard', 'supplier-products.manage',
        ],
        'referent' => [
            'mission.view', 'mission.referent-validate',
            'litige.view', 'litige.arbitrate', 'litige.vote',
            'kyc.upload', 'transactions.view',
        ],
        'livreur' => [
            'orders.view', 'deliveries.manage', 'jcode.view',
            'kyc.upload', 'transactions.view', 'parrainage.create', 'parrainage.view',
        ],
    ];

    /**
     * Actions qu'aucun retrait ne peut toucher : sans elles, l'espace du rôle
     * cesse de fonctionner pour tous ses utilisateurs, aussitôt. Toutes sont
     * exigées par une route (garde `RolePermissionCatalogTest`).
     */
    public const PROTECTED = [
        'client' => ['mission.create', 'devis.accept', 'devis.refuse', 'jalon.validate-otp'],
        'artisan' => [
            'devis.create', 'jalon.submit', 'jalon.upload-photos', 'jalon.request-otp',
            'jcode.create', 'jcode.upload-photo-materials',
        ],
        'fournisseur' => ['jcode.scan', 'supplier-products.manage'],
        'referent' => ['mission.referent-validate'],
        'livreur' => [],
    ];

    /**
     * Actions réservées : elles ne s'attribuent qu'aux rôles listés. Le
     * contrôleur d'une action réservée suppose le rôle de son appelant (une
     * mission est créée au nom du client connecté) : l'attribuer à un autre
     * rôle ferait agir ce compte sous une identité qui n'est pas la sienne.
     */
    public const RESERVED = [
        'mission.create' => ['client'],
        'mission.estimate' => ['client'],
        'devis.accept' => ['client'],
        'devis.refuse' => ['client'],
        'jalon.validate-otp' => ['client'],
        'jalon.request-otp' => ['client', 'artisan'],
        'devis.create' => ['artisan'],
        'devis.update' => ['artisan'],
        'jalon.submit' => ['artisan'],
        'jalon.upload-photos' => ['artisan'],
        'jcode.create' => ['artisan'],
        'jcode.upload-photo-materials' => ['artisan'],
        'jcode.scan' => ['fournisseur'],
        'supplier-products.manage' => ['fournisseur'],
        'mission.referent-validate' => ['referent'],
    ];

    public const INEFFECTIVE_MESSAGE = "Cette action n'est exigée par aucune fonction de l'application : elle ne se règle pas.";

    /** @var list<string>|null */
    private static ?array $effective = null;

    public function __construct(private AdminActivityLogger $audit) {}

    /**
     * Actions exigées par au moins une route (`can:<action>`), hors capacités
     * `admin.*` du backoffice.
     *
     * @return list<string>
     */
    public static function effectiveActions(): array
    {
        if (self::$effective !== null) {
            return self::$effective;
        }

        $actions = [];
        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (is_string($middleware)
                    && preg_match('/^can:([a-z][a-z.\-]*)$/', $middleware, $match) === 1
                    && ! str_starts_with($match[1], 'admin.')) {
                    $actions[$match[1]] = true;
                }
            }
        }

        $actions = array_keys($actions);
        sort($actions);

        return self::$effective = $actions;
    }

    public static function isEffective(string $permission): bool
    {
        return in_array($permission, self::effectiveActions(), true);
    }

    /** Vide le relevé des actions exigées (tests qui déclarent des routes). */
    public static function forgetEffectiveActions(): void
    {
        self::$effective = null;
    }

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
     * Actions réglables : celles du catalogue qu'une route exige.
     */
    public function getAllPermissions(): Collection
    {
        return Permission::whereIn('name', self::effectiveActions())
            ->orderBy('category')
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'category']);
    }

    /**
     * Actions réglables accordées à un rôle.
     *
     * @return list<string>
     */
    public function getRolePermissions(string $role): array
    {
        return array_values(array_intersect($this->storedPermissions($role), self::effectiveActions()));
    }

    /**
     * Le rôle s'écarte-t-il de ses droits d'origine, sur les actions réglables ?
     */
    public function isCustomized(string $role): bool
    {
        $defaults = array_values(array_intersect(self::DEFAULTS[$role] ?? [], self::effectiveActions()));
        $current = $this->getRolePermissions($role);
        sort($defaults);
        sort($current);

        return $defaults !== $current;
    }

    /**
     * Données de la partie « rôles » de l'onglet « Rôles & Actions ».
     *
     * @return array<string, mixed>
     */
    public function panelData(): array
    {
        $rolesPermissions = [];
        $customized = [];
        foreach (self::ROLES as $role) {
            $rolesPermissions[$role] = $this->getRolePermissions($role);
            $customized[$role] = $this->isCustomized($role);
        }

        return [
            'allPermissions' => $this->getAllPermissions(),
            'rolesPermissions' => $rolesPermissions,
            'protectedRolePermissions' => self::PROTECTED,
            'reservedRolePermissions' => self::RESERVED,
            'customizedRoles' => $customized,
        ];
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
     * Rend à un rôle ses droits d'origine.
     */
    public function resetRole(string $role): void
    {
        if (! in_array($role, self::ROLES, true)) {
            throw ValidationException::withMessages([
                'role' => ["Le rôle '{$role}' n'est pas valide."],
            ]);
        }

        $before = $this->storedPermissions($role);
        $defaults = self::DEFAULTS[$role];
        $ids = Permission::whereIn('name', $defaults)->pluck('id');

        DB::transaction(function () use ($role, $ids) {
            DB::table('permission_role')->where('role', $role)->delete();

            foreach ($ids as $id) {
                DB::table('permission_role')->insert([
                    'permission_id' => $id,
                    'role' => $role,
                    'created_at' => now(),
                ]);
            }
        });

        Cache::forget("role_permissions_{$role}");

        $after = $this->storedPermissions($role);
        if ($before === $after) {
            return;
        }

        $this->audit->log('role_permission.reset', null, [
            'role' => $role,
            'before' => $before,
            'after' => $after,
        ], subjectLabel: $role);
    }

    /**
     * Actions inscrites en base pour un rôle, réglables ou non.
     *
     * @return list<string>
     */
    private function storedPermissions(string $role): array
    {
        return DB::table('permission_role')
            ->join('permissions', 'permission_role.permission_id', '=', 'permissions.id')
            ->where('permission_role.role', $role)
            ->orderBy('permissions.name')
            ->pluck('permissions.name')
            ->all();
    }

    /**
     * Contrôle le rôle et l'action. Les capacités `admin.*` du backoffice ne
     * s'attribuent jamais à un rôle de l'application, et une action que
     * rien n'exige ne se règle pas.
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

        if (! self::isEffective($permissionName)) {
            throw ValidationException::withMessages([
                'permission' => [self::INEFFECTIVE_MESSAGE],
            ]);
        }

        return $permission;
    }
}
