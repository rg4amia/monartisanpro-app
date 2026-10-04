<?php

namespace App\Services\Admin;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Chantier C6 (P2-10) — gestion des capacités fines du backoffice admin.
 *
 * Les capacités sont affectées individuellement, compte admin par compte admin,
 * via la table pivot `admin_permission_user`. L'accès total s'accorde par la
 * capacité {@see self::FULL_ACCESS} ; une liste vide est refusée.
 */
class AdminPermissionService
{
    /** Capacité sentinelle : accès total au backoffice. */
    public const FULL_ACCESS = 'admin.full-access';

    /** Capacités qu'un administrateur garde toujours sur son propre compte (anti-verrouillage). */
    public const SELF_KEPT_CAPABILITIES = ['admin.roles.manage', 'admin.users.view'];

    public const EMPTY_SELECTION_MESSAGE = 'Cochez au moins une capacité, ou l\'accès total.';

    /**
     * Profils types : un jeu de capacités appliqué d'un geste à un compte,
     * que l'administrateur ajuste ensuite. Un profil n'est pas mémorisé sur
     * le compte : seules ses capacités le sont.
     */
    public const PROFILES = [
        'support' => [
            'label' => 'Support',
            'description' => 'Comptes, dossiers KYC, missions et litiges en consultation, historique des notifications, FAQ.',
            'capabilities' => [
                'admin.users.view', 'admin.users.manage', 'admin.kyc.view', 'admin.kyc.review',
                'admin.missions.view', 'admin.litiges.view', 'admin.notifications.view', 'admin.faq.manage',
            ],
        ],
        'finance' => [
            'label' => 'Finance',
            'description' => 'Transactions, versements et retraits, exports ; comptes et missions en consultation.',
            'capabilities' => [
                'admin.transactions.view', 'admin.transactions.manage', 'admin.exports',
                'admin.users.view', 'admin.missions.view',
            ],
        ],
        'moderation' => [
            'label' => 'Modération',
            'description' => 'Dossiers KYC, fournisseurs, litiges, évaluations, fraude, recrutement et annuaire.',
            'capabilities' => [
                'admin.kyc.view', 'admin.kyc.review', 'admin.fournisseurs.review',
                'admin.litiges.view', 'admin.litiges.arbitrate', 'admin.missions.view',
                'admin.evaluations.view', 'admin.fraud.view', 'admin.fraud.manage',
                'admin.recruitment.manage', 'admin.directory.manage',
            ],
        ],
        'communication' => [
            'label' => 'Communication',
            'description' => 'Annonces, messages push et SMS, campagnes, site vitrine, FAQ, promotions et parrainage.',
            'capabilities' => [
                'admin.communications.manage', 'admin.notifications.manage', 'admin.notifications.broadcast',
                'admin.vitrine.manage', 'admin.whatsapp.manage', 'admin.faq.manage',
                'admin.promo.manage', 'admin.parrainage.manage',
            ],
        ],
    ];

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
                'admin.missions.manage' => 'Forcer une transition administrative de mission',
                'admin.territory.view' => 'Consulter la cartographie interactive et les statistiques territoriales',
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
                'admin.transactions.manage' => 'Gérer et valider les opérations de trésorerie et retraits cash-out',
                'admin.exports' => 'Générer les exports CSV du backoffice',
            ],
            'qualite' => [
                'admin.evaluations.view' => 'Consulter les évaluations et scores',
                'admin.fournisseurs.review' => 'Valider ou suspendre un fournisseur agréé',
            ],
            'securite' => [
                'admin.fraud.view' => 'Consulter les alertes anti-fraude et de collusion',
                'admin.fraud.manage' => 'Geler les fonds, confirmer ou classer les alertes de fraude',
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
                'admin.notifications.manage' => 'Modifier les messages push et SMS, les tester et consulter le journal des envois',
                'admin.notifications.broadcast' => 'Créer, programmer et annuler des campagnes push et SMS vers les utilisateurs',
                'admin.directory.manage' => 'Valider les disponibilités des artisans et gérer leur présence dans l\'annuaire du site',
                'admin.vitrine.manage' => 'Administrer le CMS de la vitrine et les contacts',
                'admin.whatsapp.manage' => 'Configurer le bouton WhatsApp du site et consulter les clics enregistrés',
                'admin.faq.manage' => "Gérer la FAQ d'aide et support de l'application mobile",
            ],
            'marketing' => [
                'admin.promo.manage' => 'Gérer les codes promotionnels',
                'admin.parrainage.manage' => 'Gérer les campagnes de parrainage client',
            ],
            'recrutement' => [
                'admin.recruitment.manage' => 'Modérer les offres de recrutement et piloter la publication client/fournisseur',
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
        if ($user->role !== 'admin') {
            return false;
        }

        if (! $user->email) {
            return false;
        }

        $protected = array_map(
            'mb_strtolower',
            (array) config('prosartisan.super_admins', []),
        );

        $normalizedEmail = mb_strtolower($user->email);

        // Protection des adresses administrateurs canoniques de la plateforme
        if (in_array($normalizedEmail, ['admin@prosartisan.ci', 'admin@prosartisan.net', 'admin@prosartisan.com'], true)) {
            return true;
        }

        return in_array($normalizedEmail, $protected, true);
    }

    /**
     * Le compte peut-il ouvrir ou garder une session du backoffice ?
     *
     * Un compte suspendu, banni ou anonymisé ne le peut pas. Les super
     * administrateurs protégés gardent l'accès quel que soit leur statut :
     * aucune action du backoffice ne doit pouvoir les verrouiller dehors.
     */
    public function hasBackofficeAccess(User $user): bool
    {
        if ($this->isProtectedSuperAdmin($user)) {
            return true;
        }

        return $user->isAccountActive() && $user->anonymized_at === null;
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

            // Sans aucune ligne, l'accès total reste le filet de secours
            // (Règle d'or 67) : l'écran ne permet plus d'atteindre cet état.
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

        // « Aucune capacité » n'est pas un état : sans ligne, le compte
        // retrouvait l'accès total, si bien que tout décocher l'accordait.
        if ($capabilities === []) {
            throw ValidationException::withMessages([
                'capabilities' => [self::EMPTY_SELECTION_MESSAGE],
            ]);
        }

        // Garde-fou anti-lockout : un administrateur ne se retire jamais à
        // lui-même la gestion des rôles ni la consultation des utilisateurs.
        if ($target->id === $actor->id && ! in_array(self::FULL_ACCESS, $capabilities, true)) {
            foreach (self::SELF_KEPT_CAPABILITIES as $kept) {
                if (! in_array($kept, $capabilities, true)) {
                    $capabilities[] = $kept;
                }
            }
        }

        $before = $this->storedCapabilities($target);
        $installed = Permission::whereIn('name', $capabilities)->pluck('id', 'name');

        // Ignorée en silence, une capacité sans ligne en base pouvait laisser
        // le compte sans aucune ligne — donc avec l'accès total.
        $missing = array_values(array_diff($capabilities, $installed->keys()->all()));
        if ($missing !== []) {
            throw ValidationException::withMessages([
                'capabilities' => ['Capacité non installée en base : '.implode(', ', $missing).'. Une migration doit l\'inscrire.'],
            ]);
        }

        $permissionIds = $installed->values()->all();

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
                'before' => $before,
                'after' => $capabilities,
                'capabilities' => $capabilities,
                'full_access' => in_array(self::FULL_ACCESS, $capabilities, true),
            ],
            actor: $actor,
        );
    }

    /**
     * Capacités inscrites en base pour un compte, sans le repli sur l'accès total.
     *
     * @return array<int, string>
     */
    private function storedCapabilities(User $user): array
    {
        return DB::table('admin_permission_user')
            ->join('permissions', 'admin_permission_user.permission_id', '=', 'permissions.id')
            ->where('admin_permission_user.user_id', $user->id)
            ->orderBy('permissions.name')
            ->pluck('permissions.name')
            ->all();
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
                'is_self' => $admin->id === Auth::id(),
            ])
            ->values()
            ->all();

        return [
            'adminCapabilityCatalog' => self::catalog(),
            'adminProfiles' => self::PROFILES,
            'admins' => $admins,
        ];
    }
}
