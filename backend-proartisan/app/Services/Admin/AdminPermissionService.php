<?php

namespace App\Services\Admin;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Chantier C6 (P2-10) — gestion des capacités fines du backoffice admin.
 *
 * Les capacités sont affectées individuellement, compte admin par compte admin,
 * via la table pivot `admin_permission_user`. Un admin sans aucune capacité —
 * ou porteur de {@see self::FULL_ACCESS} — dispose d'un accès total.
 */
class AdminPermissionService
{
    /** Capacité sentinelle : accès total au backoffice. */
    public const FULL_ACCESS = 'admin.full-access';

    /** Préfixe du cache des capacités effectives par utilisateur. */
    private const CACHE_PREFIX = 'admin_caps_user_';

    private const CACHE_TTL = 300;

    /**
     * Catalogue des capacités : groupe => [nom => description].
     *
     * @return array<string, array<string, string>>
     */
    public static function catalog(): array
    {
        return [
            'kyc' => [
                'admin.kyc.view' => 'Consulter les dossiers KYC et vérifications',
                'admin.kyc.review' => 'Valider ou rejeter les dossiers KYC (unitaire et groupé)',
            ],
            'missions' => [
                'admin.missions.view' => 'Consulter les missions et livraisons',
            ],
            'litiges' => [
                'admin.litiges.view' => 'Consulter les dossiers de litige',
                'admin.litiges.arbitrate' => 'Arbitrer et trancher les litiges',
            ],
            'users' => [
                'admin.users.view' => 'Consulter les comptes utilisateurs',
                'admin.users.manage' => 'Créer, modifier, suspendre un compte et geler un score',
                'admin.users.delete' => 'Supprimer définitivement un compte',
                'admin.users.impersonate' => "Se connecter en tant qu'un utilisateur (usurpation de session)",
            ],
            'rgpd' => [
                'admin.rgpd.view' => "Consulter les données personnelles d'un utilisateur (RGPD)",
                'admin.rgpd.manage' => 'Anonymiser un compte (droit à l\'effacement)',
            ],
            'finance' => [
                'admin.transactions.view' => 'Consulter les transactions et flux financiers',
                'admin.exports' => 'Générer les exports CSV du backoffice',
            ],
            'qualite' => [
                'admin.evaluations.view' => 'Consulter les évaluations et scores',
                'admin.fournisseurs.review' => 'Valider ou suspendre un fournisseur agréé',
            ],
            'plateforme' => [
                'admin.settings.manage' => 'Modifier les paramètres métier de la plateforme',
                'admin.taxonomy.manage' => 'Gérer les catégories et sous-catégories métier',
                'admin.roles.manage' => 'Gérer les rôles et les droits des administrateurs',
                'admin.audit.view' => "Consulter le journal d'audit",
                'admin.observability.view' => 'Consulter le panneau de santé opérationnelle',
                'admin.observability.manage' => 'Relancer / purger les jobs en échec',
            ],
            'communication' => [
                'admin.communications.manage' => 'Gérer les communications et annonces',
                'admin.notifications.view' => 'Consulter le centre de notifications',
                'admin.vitrine.manage' => 'Administrer le CMS de la vitrine et les contacts',
            ],
            'marketing' => [
                'admin.promo.manage' => 'Gérer les codes promotionnels',
            ],
            'intelligence' => [
                'admin.ai.manage' => 'Piloter les paramètres et coûts IA',
                'admin.llm.manage' => "Administrer l'ingestion sémantique et le pipeline RAG",
            ],
        ];
    }

    /**
     * Liste à plat de tous les noms de capacités connus (hors sentinelle).
     *
     * @return array<int, string>
     */
    public static function allCapabilityNames(): array
    {
        $names = [];

        foreach (self::catalog() as $capabilities) {
            foreach (array_keys($capabilities) as $name) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Le compte est-il un super administrateur protégé (accès total permanent,
     * non modifiable depuis le backoffice) ? Configuré via `SUPER_ADMIN_EMAILS`.
     */
    public function isProtectedSuperAdmin(User $user): bool
    {
        if ($user->role !== 'admin' || ! $user->email) {
            return false;
        }

        $protected = array_map(
            'mb_strtolower',
            (array) config('prosartisan.super_admins', []),
        );

        return in_array(mb_strtolower($user->email), $protected, true);
    }

    /**
     * Capacités effectives d'un administrateur.
     *
     * @return array<int, string> Liste des capacités, ou `['*']` pour un accès total.
     */
    public function capabilitiesFor(User $user): array
    {
        if ($user->role !== 'admin') {
            return [];
        }

        if ($this->isProtectedSuperAdmin($user)) {
            return ['*'];
        }

        return Cache::remember(self::CACHE_PREFIX.$user->id, self::CACHE_TTL, function () use ($user) {
            $names = DB::table('admin_permission_user')
                ->join('permissions', 'admin_permission_user.permission_id', '=', 'permissions.id')
                ->where('admin_permission_user.user_id', $user->id)
                ->pluck('permissions.name')
                ->all();

            if ($names === [] || in_array(self::FULL_ACCESS, $names, true)) {
                return ['*'];
            }

            return array_values(array_filter($names, static fn ($n) => str_starts_with($n, 'admin.')));
        });
    }

    public function userCan(User $user, string $capability): bool
    {
        $granted = $this->capabilitiesFor($user);

        return $granted === ['*'] || in_array($capability, $granted, true);
    }

    /**
     * Remplace intégralement les capacités d'un administrateur.
     *
     * @param  array<int, string>  $capabilities
     */
    public function sync(User $target, array $capabilities, User $actor): void
    {
        if ($target->role !== 'admin') {
            throw ValidationException::withMessages([
                'user' => ['Seuls les comptes administrateurs peuvent recevoir des droits de backoffice.'],
            ]);
        }

        if ($this->isProtectedSuperAdmin($target)) {
            throw ValidationException::withMessages([
                'user' => ['Ce super administrateur dispose d\'un accès total permanent et ne peut être restreint.'],
            ]);
        }

        $allowed = array_merge(self::allCapabilityNames(), [self::FULL_ACCESS]);
        $capabilities = array_values(array_unique(array_intersect($capabilities, $allowed)));

        $permissionIds = Permission::whereIn('name', $capabilities)->pluck('id')->all();

        DB::transaction(function () use ($target, $permissionIds) {
            DB::table('admin_permission_user')->where('user_id', $target->id)->delete();

            foreach ($permissionIds as $permissionId) {
                DB::table('admin_permission_user')->insert([
                    'user_id' => $target->id,
                    'permission_id' => $permissionId,
                    'created_at' => now(),
                ]);
            }
        });

        $this->forget($target);

        app(AdminActivityLogger::class)->log(
            'admin.permissions_updated',
            $target,
            [
                'capabilities' => $capabilities,
                'full_access' => in_array(self::FULL_ACCESS, $capabilities, true) || $capabilities === [],
            ],
            actor: $actor,
        );
    }

    public function forget(User $user): void
    {
        Cache::forget(self::CACHE_PREFIX.$user->id);
    }

    /**
     * Données de l'onglet « Rôles & Actions » pour la gestion des admins.
     *
     * @return array<string, mixed>
     */
    public function panelData(): array
    {
        $admins = User::query()
            ->where('role', 'admin')
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone'])
            ->map(fn (User $admin) => [
                'id' => $admin->id,
                'name' => $admin->name,
                'email' => $admin->email,
                'phone' => $admin->phone,
                'capabilities' => $this->capabilitiesFor($admin),
                'protected' => $this->isProtectedSuperAdmin($admin),
            ])
            ->values()
            ->all();

        return [
            'adminCapabilityCatalog' => self::catalog(),
            'admins' => $admins,
        ];
    }
}
