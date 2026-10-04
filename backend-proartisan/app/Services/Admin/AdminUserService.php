<?php

namespace App\Services\Admin;

use App\Models\ArtisanProfile;
use App\Models\Trade;
use App\Models\User;
use App\Services\AccountEngagementService;
use App\Services\KycService;
use App\Services\NotificationService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Logique métier de gestion des comptes depuis le backoffice.
 *
 * Les controllers ne font que valider (FormRequest) et déléguer ici :
 * la règle d'or du projet interdit toute logique métier dans les controllers.
 */
class AdminUserService
{
    /** Capacité requise pour créer, promouvoir ou modifier un compte administrateur. */
    private const ADMIN_ACCOUNTS_CAPABILITY = 'admin.roles.manage';

    /**
     * Rôles créés ou nommés par un administrateur, sans dossier d'identité à
     * déposer dans l'application : leur statut KYC se règle à la main.
     */
    public const STAFF_ROLES = ['referent', 'admin'];

    public function __construct(
        private AdminActivityLogger $audit,
        private KycService $kycDocuments,
        private AdminPermissionService $permissions,
        private AccountEngagementService $engagement,
        private NotificationService $notifications,
    ) {}

    /**
     * Un compte se crée toujours actif : la suspension passe par
     * {@see toggleStatus}, qui enregistre le motif et la date.
     *
     * @param  array<string, mixed>  $data  Données déjà validées par StoreUserRequest.
     */
    public function create(array $data): User
    {
        if ($data['role'] === 'admin' && ! $this->canManageAdminAccounts($this->actor())) {
            throw ValidationException::withMessages([
                'role' => ['Créer un compte administrateur exige le droit de gérer les rôles et les droits des administrateurs.'],
            ]);
        }

        $data['password'] = Hash::make($data['password']);
        $data['score_frozen'] = (bool) ($data['score_frozen'] ?? false);
        $data['account_status'] = 'actif';
        // Seule la revue KYC active un compte de l'application : le
        // formulaire ne règle le statut que d'un Référent ou d'un administrateur.
        $data['kyc_status'] = in_array($data['role'], self::STAFF_ROLES, true)
            ? ($data['kyc_status'] ?? 'actif')
            : 'en_attente';

        $user = User::create($data);

        if ($user->role === 'admin') {
            $this->grantExplicitFullAccess($user);
        }

        $this->audit->log('user.created', $user, [
            'role' => $user->role,
            'kyc_status' => $user->kyc_status,
        ]);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $data  Données déjà validées par UpdateUserRequest.
     */
    public function update(User $user, array $data): User
    {
        $actor = $this->actor();

        try {
            $this->guardNotAnonymized($user);
            $this->guardProtected($user, $actor);
        } catch (\LogicException $e) {
            throw ValidationException::withMessages(['name' => [$e->getMessage()]]);
        }

        $this->guardRoleChange($user, $data, $actor);
        $this->guardSuperAdminIdentity($user, $data);

        $newRole = $data['role'] ?? $user->role;
        $roleChanged = $newRole !== $user->role;
        $phoneChanged = array_key_exists('phone', $data) && $data['phone'] !== $user->phone;
        $previousPhone = $user->phone;
        $kycStatus = $data['kyc_status'] ?? null;
        $shopName = array_key_exists('fournisseur_shop_name', $data) ? $data['fournisseur_shop_name'] : false;
        unset($data['role'], $data['kyc_status'], $data['fournisseur_shop_name']);

        // Statut KYC : réglé à la main pour un Référent ou un administrateur
        // seulement. Un changement de rôle fixe lui-même le statut.
        if (! $roleChanged && $kycStatus !== null && in_array($newRole, self::STAFF_ROLES, true)) {
            $data['kyc_status'] = $kycStatus;
        }

        $before = $user->only(['name', 'email', 'phone', 'role', 'kyc_status', 'score_frozen']);

        if ($roleChanged) {
            try {
                $this->changeRole($user, $newRole, $actor, $kycStatus);
            } catch (\LogicException $e) {
                throw ValidationException::withMessages(['role' => [$e->getMessage()]]);
            }
        }

        $passwordChanged = ! empty($data['password']);

        if ($passwordChanged) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $data['score_frozen'] = (bool) ($data['score_frozen'] ?? false);

        // La photo, les pièces KYC et le secteur/métier fournisseur ne sont
        // pas des colonnes de `users` assignables en masse : la photo et les
        // pièces suivent leur propre circuit de stockage (disque privé), le
        // secteur/métier vit sur le profil `fournisseurs_agrees` du compte.
        $photo = $data['photo'] ?? null;
        $documents = $data['documents'] ?? [];
        $fournisseurSectorId = array_key_exists('fournisseur_sector_id', $data) ? $data['fournisseur_sector_id'] : false;
        $fournisseurTradeId = array_key_exists('fournisseur_trade_id', $data) ? $data['fournisseur_trade_id'] : false;
        unset($data['photo'], $data['documents'], $data['fournisseur_sector_id'], $data['fournisseur_trade_id']);

        $user->update($data);

        if ($phoneChanged) {
            $this->afterPhoneChangedBySupport($user, $previousPhone);
        }

        if ($photo instanceof UploadedFile) {
            $this->updatePhoto($user, $photo);
        }

        $updatedDocuments = [];
        foreach (['cni', 'selfie'] as $type) {
            if (($documents[$type] ?? null) instanceof UploadedFile) {
                $this->kycDocuments->uploadDocument($user, $type, $documents[$type]);
                $updatedDocuments[] = $type;
            }
        }

        if (($fournisseurSectorId !== false || $fournisseurTradeId !== false) && $user->role === 'fournisseur') {
            $this->updateFournisseurCategory($user, $fournisseurSectorId, $fournisseurTradeId);
        }

        if (is_string($shopName) && trim($shopName) !== '' && $user->role === 'fournisseur') {
            $user->fournisseurAgree?->update(['nom_boutique' => trim($shopName)]);
        }

        $this->audit->log('user.updated', $user, [
            'before' => $before,
            'after' => $user->only(['name', 'email', 'phone', 'role', 'kyc_status', 'score_frozen']),
            'password_changed' => $passwordChanged,
            'phone_changed' => $phoneChanged,
            'sessions_closed' => $phoneChanged || $roleChanged,
            'photo_updated' => $photo instanceof UploadedFile,
            'documents_updated' => $updatedDocuments,
            'fournisseur_sector_id' => $fournisseurSectorId !== false ? $fournisseurSectorId : null,
            'fournisseur_trade_id' => $fournisseurTradeId !== false ? $fournisseurTradeId : null,
        ]);

        return $user;
    }

    /**
     * Seul circuit de changement de rôle, pour le backoffice comme pour la
     * route d'administration de l'application : compte libre de tout
     * engagement, KYC remis en attente (un dossier validé pour un rôle ne vaut
     * pas pour un autre), sessions fermées, ligne d'audit.
     *
     * @throws \LogicException Compte engagé : message listant ce qui bloque.
     */
    public function changeRole(User $user, string $newRole, ?User $actor, ?string $staffKycStatus = null): User
    {
        $previousRole = $user->role;

        if ($previousRole === $newRole) {
            return $user;
        }

        $this->engagement->assertFree($user, 'changer de rôle');

        DB::transaction(function () use ($user, $newRole, $staffKycStatus): void {
            $user->update([
                'role' => $newRole,
                'kyc_status' => in_array($newRole, self::STAFF_ROLES, true) ? ($staffKycStatus ?? 'actif') : 'en_attente',
                'kyc_rejected_at' => null,
            ]);

            if ($newRole === 'artisan') {
                ArtisanProfile::query()->firstOrCreate(
                    ['user_id' => $user->id],
                    ['intervient_la_nuit' => false],
                );
            }

            $user->tokens()->delete();
        });

        $this->syncAdminAccessAfterRoleChange($user, $previousRole);

        $this->audit->log('user.role.updated', $user, [
            'before' => $previousRole,
            'after' => $newRole,
            'kyc_status' => $user->kyc_status,
            'tokens_revoked' => true,
        ], actor: $actor);

        return $user;
    }

    /**
     * Le support a changé le numéro d'un compte (carte SIM perdue) : les
     * sessions ouvertes avec l'ancien numéro sont fermées et le titulaire est
     * prévenu sur le nouveau.
     */
    private function afterPhoneChangedBySupport(User $user, ?string $previousPhone): void
    {
        $user->tokens()->delete();

        try {
            $this->notifications->notify($user, 'compte.telephone_modifie.utilisateur', [
                'telephone' => (string) $user->phone,
            ], ['ancien_numero_masque' => $previousPhone !== null ? substr($previousPhone, -2) : null]);
        } catch (\Throwable $e) {
            Log::warning('[Comptes] Notification de changement de numéro non envoyée : '.$e->getMessage());
        }
    }

    /**
     * Met à jour le secteur d'activité et/ou la sous-catégorie (métier) d'un
     * fournisseur. Un métier n'appartenant pas au secteur retenu (effectif
     * après cette mise à jour) est ignoré plutôt qu'enregistré incohérent :
     * l'admin ne peut choisir un métier que via la liste déjà filtrée par
     * secteur côté formulaire, mais un appel direct pourrait tenter l'incohérence.
     */
    private function updateFournisseurCategory(User $user, int|false|null $sectorId, int|false|null $tradeId): void
    {
        $fournisseurAgree = $user->fournisseurAgree;
        if (! $fournisseurAgree) {
            return;
        }

        $effectiveSectorId = $sectorId !== false ? $sectorId : $fournisseurAgree->sector_id;

        if ($tradeId !== false && $tradeId !== null) {
            $tradeBelongsToSector = Trade::where('id', $tradeId)->where('sector_id', $effectiveSectorId)->exists();
            if (! $tradeBelongsToSector) {
                $tradeId = null;
            }
        }

        $update = [];
        if ($sectorId !== false) {
            $update['sector_id'] = $sectorId;
        }
        if ($tradeId !== false) {
            $update['trade_id'] = $tradeId;
        }

        if ($update !== []) {
            $fournisseurAgree->update($update);
        }
    }

    /**
     * Remplace la photo de profil : supprime l'ancien fichier une fois le
     * nouveau enregistré, jamais avant (pas de fenêtre sans photo en cas
     * d'échec d'écriture).
     */
    private function updatePhoto(User $user, UploadedFile $file): void
    {
        $previousPath = $user->photo_path;

        $path = $file->store('avatars', 'local');
        $user->update(['photo_path' => $path]);

        if ($previousPath !== null) {
            Storage::disk('local')->delete($previousPath);
        }
    }

    public function delete(User $user): void
    {
        $this->guardNotSelf($user, 'Vous ne pouvez pas supprimer votre propre compte administrateur.');
        $this->guardProtected($user, $this->actor());
        $this->engagement->assertFree($user, 'être supprimé');

        $this->audit->log('user.deleted', $user, [
            'role' => $user->role,
            'phone' => $user->phone,
        ]);

        $user->delete();
    }

    /**
     * Restaure un compte supprimé : son numéro restait réservé et son
     * titulaire ne pouvait plus ni se connecter ni se réinscrire.
     *
     * @throws \LogicException
     */
    public function restore(User $user): User
    {
        if (! $user->trashed()) {
            throw new \LogicException("Ce compte n'est pas supprimé.");
        }

        $user->restore();

        $this->audit->log('user.restored', $user, [
            'role' => $user->role,
            'phone' => $user->phone,
        ]);

        return $user;
    }

    /**
     * Seule voie de changement du statut d'un compte : elle tient à jour le
     * motif et la date de blocage, et laisse une ligne d'audit.
     *
     * @param  array{account_status: string, account_status_reason?: string|null}  $data
     */
    public function toggleStatus(User $user, array $data): User
    {
        $this->guardNotSelf($user, 'Vous ne pouvez pas désactiver votre propre compte administrateur.');
        $this->guardNotAnonymized($user);
        $this->guardProtected($user, $this->actor());

        $previous = $user->account_status ?? 'actif';
        $suspended = $data['account_status'] === 'suspendu';
        $reason = $suspended ? ($data['account_status_reason'] ?? null) : null;

        $user->update([
            'account_status' => $data['account_status'],
            'account_status_reason' => $reason,
            'blocked_at' => $suspended ? now() : null,
        ]);

        // Une suspension ferme les sessions de l'application : le compte ne
        // garde aucun jeton utilisable.
        if ($suspended) {
            $user->tokens()->delete();
        }

        $this->audit->log('user.status_changed', $user, [
            'previous_status' => $previous,
            'account_status' => $data['account_status'],
            'reason' => $reason,
            'sessions_closed' => $suspended,
        ]);

        return $user;
    }

    /**
     * Changement de statut groupé (Règle d'or 21). Chaque compte passe par
     * {@see toggleStatus} : mêmes gardes, une ligne d'audit par compte, puis
     * une ligne récapitulative. Un compte refusé n'interrompt pas le lot.
     *
     * @param  array<int>  $ids
     * @param  array{account_status: string, account_status_reason?: string|null}  $data
     * @return array{updated: int, skipped: array<int, string>} Comptes modifiés, et motif du refus par identifiant.
     */
    public function bulkToggleStatus(array $ids, array $data): array
    {
        $updatedIds = [];
        $skipped = [];

        foreach (User::whereIn('id', array_unique(array_map('intval', $ids)))->get() as $user) {
            try {
                $this->toggleStatus($user, $data);
                $updatedIds[] = $user->id;
            } catch (\LogicException $e) {
                $skipped[$user->id] = $e->getMessage();
            } catch (\Throwable $e) {
                Log::error("bulkToggleStatus user {$user->id}: ".$e->getMessage());
                $skipped[$user->id] = 'Erreur interne.';
            }
        }

        $this->audit->log('user.bulk_status_changed', null, [
            'account_status' => $data['account_status'],
            'reason' => $data['account_status_reason'] ?? null,
            'user_ids' => $updatedIds,
            'count' => count($updatedIds),
            'skipped' => $skipped,
        ]);

        return ['updated' => count($updatedIds), 'skipped' => $skipped];
    }

    /**
     * Gel / dégel du Score ProsArtisan d'un artisan.
     *
     * @return bool Nouvel état : true = gelé, false = dégelé.
     */
    public function toggleScoreFreeze(User $user): bool
    {
        if ($user->role !== 'artisan') {
            throw new \LogicException('Seuls les scores des artisans peuvent être gelés/dégelés.');
        }

        $user->update(['score_frozen' => ! $user->score_frozen]);

        $frozen = (bool) $user->score_frozen;

        $this->audit->log('user.score_freeze_toggled', $user, [
            'frozen' => $frozen,
        ]);

        return $frozen;
    }

    public function reviewCnmci(User $user, string $decision): User
    {
        if ($user->role !== 'artisan') {
            throw new \LogicException('Seuls les artisans peuvent posséder un profil CNMCI.');
        }

        $user->update(['cnmci_status' => $decision]);

        $this->audit->log('user.cnmci_reviewed', $user, [
            'decision' => $decision,
        ]);

        return $user;
    }

    /**
     * Garde des comptes administrateurs, commune à la modification, au
     * changement de statut, à la suppression et à l'anonymisation.
     *
     * Un administrateur sans capacité affectée a l'accès total : sans cette
     * garde, la capacité « gérer les utilisateurs » suffisait à prendre le
     * contrôle d'un super administrateur ou d'un autre administrateur.
     *
     * @throws \LogicException
     */
    public function guardProtected(User $target, ?User $actor): void
    {
        if ($target->role !== 'admin' || ($actor !== null && $actor->id === $target->id)) {
            return;
        }

        if ($this->permissions->isProtectedSuperAdmin($target)) {
            throw new \LogicException('Ce compte est un super administrateur protégé : lui seul peut le modifier.');
        }

        if (! $this->canManageAdminAccounts($actor)) {
            throw new \LogicException('Agir sur un compte administrateur exige le droit de gérer les rôles et les droits des administrateurs.');
        }
    }

    /**
     * Une anonymisation est irréversible : le compte ne se réactive pas et ne
     * se réattribue pas à une autre personne.
     *
     * @throws \LogicException
     */
    public function guardNotAnonymized(User $user): void
    {
        if ($user->anonymized_at !== null) {
            throw new \LogicException('Ce compte est anonymisé : il ne peut plus être modifié.');
        }
    }

    /**
     * Donner ou retirer le rôle administrateur est réservé, et personne ne
     * change son propre rôle.
     *
     * @param  array<string, mixed>  $data
     */
    private function guardRoleChange(User $user, array $data, ?User $actor): void
    {
        $newRole = $data['role'] ?? $user->role;

        if ($newRole === $user->role) {
            return;
        }

        if ($actor !== null && $actor->id === $user->id) {
            throw ValidationException::withMessages([
                'role' => ['Vous ne pouvez pas changer votre propre rôle.'],
            ]);
        }

        if ($this->permissions->isProtectedSuperAdmin($user)) {
            throw ValidationException::withMessages([
                'role' => ["Le rôle d'un super administrateur protégé ne se modifie pas."],
            ]);
        }

        if (($newRole === 'admin' || $user->role === 'admin') && ! $this->canManageAdminAccounts($actor)) {
            throw ValidationException::withMessages([
                'role' => ['Donner ou retirer le rôle administrateur exige le droit de gérer les rôles et les droits des administrateurs.'],
            ]);
        }
    }

    /**
     * La protection d'un super administrateur tient à son adresse e-mail et à
     * son rôle : il ne change ni l'une ni l'autre, même sur son propre compte.
     *
     * @param  array<string, mixed>  $data
     */
    private function guardSuperAdminIdentity(User $user, array $data): void
    {
        if (! $this->permissions->isProtectedSuperAdmin($user)) {
            return;
        }

        if (array_key_exists('email', $data) && mb_strtolower((string) $data['email']) !== mb_strtolower((string) $user->email)) {
            throw ValidationException::withMessages([
                'email' => ["L'adresse e-mail d'un super administrateur protégé ne se modifie pas."],
            ]);
        }

        if (($data['role'] ?? 'admin') !== 'admin') {
            throw ValidationException::withMessages([
                'role' => ["Le rôle d'un super administrateur protégé ne se modifie pas."],
            ]);
        }
    }

    /**
     * Un compte promu administrateur reçoit l'accès total par une ligne
     * explicite ; un compte qui cesse de l'être perd ses capacités.
     */
    private function syncAdminAccessAfterRoleChange(User $user, string $previousRole): void
    {
        if ($user->role === 'admin') {
            $this->grantExplicitFullAccess($user);

            return;
        }

        if ($previousRole === 'admin') {
            DB::table('admin_permission_user')->where('user_id', $user->id)->delete();
            $this->permissions->forget($user);
        }
    }

    /**
     * L'accès total d'un nouvel administrateur s'écrit en base : il ne repose
     * pas sur l'absence de ligne, et se restreint ensuite dans « Rôles & Actions ».
     */
    private function grantExplicitFullAccess(User $user): void
    {
        $permissionId = DB::table('permissions')->where('name', AdminPermissionService::FULL_ACCESS)->value('id');

        if ($permissionId === null || DB::table('admin_permission_user')->where('user_id', $user->id)->exists()) {
            return;
        }

        DB::table('admin_permission_user')->insert([
            'user_id' => $user->id,
            'permission_id' => $permissionId,
            'created_at' => now(),
        ]);

        $this->permissions->forget($user);
    }

    private function canManageAdminAccounts(?User $actor): bool
    {
        return $actor !== null && $actor->adminCan(self::ADMIN_ACCOUNTS_CAPABILITY);
    }

    private function actor(): ?User
    {
        $actor = Auth::user();

        return $actor instanceof User ? $actor : null;
    }

    private function guardNotSelf(User $user, string $message): void
    {
        if (Auth::id() === $user->id) {
            throw new \LogicException($message);
        }
    }
}
