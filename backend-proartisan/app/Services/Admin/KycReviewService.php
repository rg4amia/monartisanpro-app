<?php

namespace App\Services\Admin;

use App\Models\KycDocument;
use App\Models\User;
use App\Services\AccountEngagementService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Revue d'un dossier KYC par un administrateur (Chantier 27, lot C).
 *
 * Le compte et ses deux pièces changent ensemble : un compte « actif » sans
 * pièce, ou dont les pièces restent « rejetées », n'a aucun sens pour qui
 * relit le dossier ensuite.
 */
class KycReviewService
{
    public const MISSING_DOCUMENTS_MESSAGE = 'Ce dossier ne contient pas les deux pièces.';

    public function __construct(
        private NotificationService $notificationService,
        private AdminActivityLogger $audit,
        private AccountEngagementService $engagement,
    ) {}

    /**
     * @throws \LogicException Dossier qui ne peut pas être traité, message en français.
     */
    public function review(User $admin, User $user, string $decision, ?string $rejectionReason = null): User
    {
        // Un compte anonymisé ou supprimé ne se réactive par aucune voie (Règle d'or 22).
        if ($user->anonymized_at !== null) {
            throw new \LogicException('Ce compte est anonymisé : son dossier KYC ne se traite plus.');
        }
        if ($user->trashed()) {
            throw new \LogicException('Ce compte est supprimé : son dossier KYC ne se traite plus.');
        }

        $approved = $decision === 'approuve';
        $documents = $this->currentDocuments($user);

        if ($approved && count($documents) < 2) {
            throw new \LogicException(self::MISSING_DOCUMENTS_MESSAGE);
        }

        $reason = $approved ? null : $rejectionReason;
        $wasActive = $user->kyc_status === 'actif';
        $ongoingMissions = $wasActive && ! $approved ? $this->engagement->ongoingMissionsCount($user) : 0;

        DB::transaction(function () use ($admin, $user, $approved, $reason, $documents): void {
            // Les pièces courantes suivent la décision, quel que soit leur
            // statut précédent : approuver après un rejet ne les laisse pas « rejetées ».
            foreach ($documents as $document) {
                $document->update([
                    'statut' => $approved ? 'approuve' : 'rejete',
                    'reviewed_by' => $admin->id,
                    'rejection_reason' => $reason,
                    'reviewed_at' => now(),
                ]);
            }

            // La date du rejet survit au retour du dossier « en attente » : elle
            // interdit à l'analyse automatique de défaire une décision humaine.
            $user->update([
                'kyc_status' => $approved ? 'actif' : 'rejete',
                'kyc_rejected_at' => $approved ? null : now(),
            ]);
        });

        if ($approved) {
            $this->notificationService->notify($user, 'kyc.valide.utilisateur', [], ['decision' => $decision]);
        } else {
            $this->notificationService->notify(
                $user,
                'kyc.rejete.utilisateur',
                ['motif' => (string) $reason],
                ['decision' => $decision],
            );
        }

        // Retirer la validation d'un compte actif ferme ses sessions : il ne
        // doit plus agir avec un jeton obtenu quand son identité était admise.
        if ($wasActive && ! $approved) {
            $user->tokens()->delete();
        }

        $this->audit->log('kyc.reviewed', $user, [
            'decision' => $decision,
            'rejection_reason' => $reason,
            'compte_actif_avant' => $wasActive,
            'sessions_fermees' => $wasActive && ! $approved,
            'missions_en_cours' => $ongoingMissions,
        ], actor: $admin);

        return $user->fresh(['kycDocuments']);
    }

    /**
     * Revue groupée (Règle d'or 21). Chaque dossier passe par {@see review} ;
     * un dossier refusé n'interrompt pas le lot.
     *
     * @param  array<int>  $ids
     * @return array{processed: int, skipped: array<int, string>} Dossiers traités, et motif du refus par identifiant.
     */
    public function bulkReview(User $admin, array $ids, string $decision, ?string $rejectionReason = null): array
    {
        $done = 0;
        $skipped = [];

        foreach (User::whereIn('id', $ids)->where('kyc_status', 'en_attente')->get() as $user) {
            try {
                $this->review($admin, $user, $decision, $rejectionReason);
                $done++;
            } catch (\LogicException $e) {
                $skipped[$user->id] = $e->getMessage();
            } catch (\Throwable $e) {
                Log::error("bulkReviewKyc user {$user->id}: ".$e->getMessage());
                $skipped[$user->id] = 'Erreur interne.';
            }
        }

        $this->audit->log('kyc.bulk_reviewed', null, [
            'decision' => $decision,
            'requested' => count($ids),
            'processed' => $done,
            'skipped' => $skipped,
        ], actor: $admin);

        return ['processed' => $done, 'skipped' => $skipped];
    }

    /**
     * Pièce d'identité et selfie courants : la plus récente de chaque type.
     *
     * @return array<string, KycDocument> Indexé par type ; une pièce absente n'y figure pas.
     */
    private function currentDocuments(User $user): array
    {
        return KycDocument::where('user_id', $user->id)
            ->whereIn('type', ['cni', 'selfie'])
            ->orderByDesc('id')
            ->get()
            ->unique('type')
            ->keyBy('type')
            ->all();
    }
}
