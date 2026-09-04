<?php

namespace App\Services\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Logique métier de gestion des comptes depuis le backoffice.
 *
 * Les controllers ne font que valider (FormRequest) et déléguer ici :
 * la règle d'or du projet interdit toute logique métier dans les controllers.
 */
class AdminUserService
{
    public function __construct(private AdminActivityLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data  Données déjà validées par StoreUserRequest.
     */
    public function create(array $data): User
    {
        $data['password'] = Hash::make($data['password']);
        $data['score_frozen'] = (bool) ($data['score_frozen'] ?? false);

        $user = User::create($data);

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
        $passwordChanged = ! empty($data['password']);

        if ($passwordChanged) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $data['score_frozen'] = (bool) ($data['score_frozen'] ?? false);

        $before = $user->only(['name', 'email', 'phone', 'role', 'kyc_status', 'score_frozen']);

        $user->update($data);

        $this->audit->log('user.updated', $user, [
            'before' => $before,
            'after' => $user->only(['name', 'email', 'phone', 'role', 'kyc_status', 'score_frozen']),
            'password_changed' => $passwordChanged,
        ]);

        return $user;
    }

    public function delete(User $user): void
    {
        $this->guardNotSelf($user, 'Vous ne pouvez pas supprimer votre propre compte administrateur.');

        $this->audit->log('user.deleted', $user, [
            'role' => $user->role,
            'phone' => $user->phone,
        ]);

        $user->delete();
    }

    /**
     * @param  array{account_status: string, account_status_reason?: string|null}  $data
     */
    public function toggleStatus(User $user, array $data): User
    {
        $this->guardNotSelf($user, 'Vous ne pouvez pas désactiver votre propre compte administrateur.');

        $suspended = $data['account_status'] === 'suspendu';

        $user->update([
            'account_status' => $data['account_status'],
            'account_status_reason' => $data['account_status_reason'] ?? null,
            'blocked_at' => $suspended ? now() : null,
        ]);

        $this->audit->log('user.status_changed', $user, [
            'account_status' => $data['account_status'],
            'reason' => $data['account_status_reason'] ?? null,
        ]);

        return $user;
    }

    /**
     * Changement de statut groupé (Chantier C5 / P1-9). Ignore silencieusement
     * le compte de l'admin courant et journalise une seule ligne récapitulative.
     *
     * @param  array<int>  $ids
     * @param  array{account_status: string, account_status_reason?: string|null}  $data
     * @return int Nombre de comptes effectivement modifiés.
     */
    public function bulkToggleStatus(array $ids, array $data): int
    {
        $ids = array_values(array_filter($ids, fn ($id) => (int) $id !== (int) Auth::id()));

        if ($ids === []) {
            return 0;
        }

        $suspended = $data['account_status'] === 'suspendu';

        $updated = User::whereIn('id', $ids)->update([
            'account_status' => $data['account_status'],
            'account_status_reason' => $data['account_status_reason'] ?? null,
            'blocked_at' => $suspended ? now() : null,
        ]);

        $this->audit->log('user.bulk_status_changed', null, [
            'account_status' => $data['account_status'],
            'reason' => $data['account_status_reason'] ?? null,
            'user_ids' => $ids,
            'count' => $updated,
        ]);

        return $updated;
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

    private function guardNotSelf(User $user, string $message): void
    {
        if (Auth::id() === $user->id) {
            throw new \LogicException($message);
        }
    }
}
