<?php

namespace App\Services;

use App\Enums\WalletType;
use App\Exceptions\MissionActionException;
use App\Models\Mission;
use App\Models\Setting;
use App\Models\User;
use App\States\Mission\CancelledState;
use App\States\Mission\DraftState;
use App\States\Mission\FundedLockedState;
use App\States\Mission\PendingArtisanAcceptanceState;
use App\States\Mission\PendingFundingState;
use Illuminate\Support\Facades\DB;

/**
 * Annulation d'une mission par son client (Chantier 19).
 *
 * - Avant tout paiement : sans frais.
 * - Mission financée dont le chantier n'a pas commencé : le séquestre est
 *   rendu au client, moins une pénalité retenue par la plateforme
 *   (`settings.mission_cancellation_penalty_rate`, 7 % par défaut).
 * - Chantier commencé (étape soumise, bon matériel utilisé) : refus, le
 *   désaccord passe par un litige, qui sait répartir les fonds.
 *
 * Tous les montants sont établis ici, sur la part de la mission dans le
 * séquestre : rien de ce que l'application envoie n'entre dans le calcul
 * (Règle d'or 36).
 */
class MissionCancellationService
{
    private const FREE_STATES = [DraftState::class, PendingArtisanAcceptanceState::class, PendingFundingState::class];

    public function __construct(
        private WalletService $walletService,
        private MissionLifecycleService $lifecycle,
        private NotificationService $notificationService,
    ) {}

    public function penaltyRate(): float
    {
        return min(100.0, max(0.0, (float) Setting::getValueByKey('mission_cancellation_penalty_rate', 7)));
    }

    /**
     * Ce que coûterait l'annulation, sans rien modifier.
     *
     * @return array{allowed: bool, reason: ?string, funded: bool, escrow: int, penalty_rate: float, penalty: int, refund: int}
     */
    public function preview(Mission $mission, User $client): array
    {
        $this->assertOwner($mission, $client);

        $funded = $mission->status instanceof FundedLockedState;
        $amounts = $funded ? $this->amounts($mission) : null;

        return [
            'allowed' => ($reason = $this->refusal($mission)) === null,
            'reason' => $reason,
            'funded' => $funded,
            'escrow' => $amounts['escrow'] ?? 0,
            'penalty_rate' => $funded ? $this->penaltyRate() : 0.0,
            'penalty' => $amounts['penalty'] ?? 0,
            'refund' => $amounts['refund'] ?? 0,
        ];
    }

    /**
     * Annule la mission. Idempotente : une mission déjà annulée est renvoyée
     * telle quelle, sans second remboursement.
     */
    public function cancel(Mission $mission, User $client, ?string $reason = null): Mission
    {
        $this->assertOwner($mission, $client);

        $notifyArtisan = null;
        $refund = null;

        $mission = DB::transaction(function () use ($mission, $client, $reason, &$notifyArtisan, &$refund) {
            $mission = Mission::query()->lockForUpdate()->findOrFail($mission->id);

            if ($mission->status instanceof CancelledState) {
                return $mission;
            }

            if ($refusal = $this->refusal($mission)) {
                throw new MissionActionException($refusal);
            }

            $notifyArtisan = $mission->artisan;
            $attributes = [
                'cancelled_at' => now(),
                'cancelled_by' => $client->id,
                'cancellation_reason' => $reason !== null && trim($reason) !== '' ? mb_substr(trim($reason), 0, 255) : null,
            ];

            if ($mission->status instanceof FundedLockedState) {
                $amounts = $this->amounts($mission);
                $refund = $amounts;

                // Les bons matériels encore actifs ne doivent plus être servis.
                $mission->jcodes()->where('statut', 'actif')->update(['statut' => 'expire']);

                $this->walletService->refundClientOnCancellation($mission, $amounts);

                $attributes += [
                    'cancellation_penalty' => $amounts['penalty'],
                    'cancellation_refund' => $amounts['refund'],
                    'cancellation_penalty_rate' => $amounts['penalty_rate'],
                ];
            } else {
                // Aucune étape n'a pu commencer : paiements en attente abandonnés.
                $mission->transactions()->where('statut', 'en_attente')->update(['statut' => 'echoue']);
            }

            $mission->update($attributes);

            $this->lifecycle->transition(
                $mission,
                CancelledState::class,
                $client,
                $attributes['cancellation_reason'] ?? 'Annulation par le client',
                array_filter([
                    'penalite' => $refund['penalty'] ?? null,
                    'rembourse' => $refund['refund'] ?? null,
                    'taux_penalite' => $refund['penalty_rate'] ?? null,
                ], fn ($value) => $value !== null),
            );

            return $mission;
        });

        if ($notifyArtisan) {
            $this->notificationService->notify($notifyArtisan, 'mission.annulee.artisan', ['mission' => $mission->id], ['mission_id' => $mission->id]);
        }

        if ($refund !== null) {
            $this->notificationService->notify(
                $client,
                'mission.annulee_remboursee.client',
                [
                    'mission' => $mission->id,
                    'montant' => number_format($refund['refund'], 0, ',', ' '),
                    'penalite' => number_format($refund['penalty'], 0, ',', ' '),
                ],
                ['mission_id' => $mission->id]
            );
        }

        return $mission->refresh();
    }

    private function assertOwner(Mission $mission, User $client): void
    {
        if ((int) $mission->client_id !== (int) $client->id) {
            throw new MissionActionException('Accès refusé.', 403);
        }
    }

    /**
     * Motif de refus de l'annulation, ou `null` si elle est permise.
     */
    private function refusal(Mission $mission): ?string
    {
        if ($mission->status instanceof CancelledState) {
            return null;
        }

        foreach (self::FREE_STATES as $state) {
            if ($mission->status instanceof $state) {
                return $mission->transactions()->where('statut', 'confirme')->exists()
                    ? 'Un paiement a déjà été confirmé pour cette mission : finalisez le devis ou contactez le support ProsArtisan.'
                    : null;
            }
        }

        if (! $mission->status instanceof FundedLockedState) {
            return 'Le chantier a commencé : cette mission ne peut plus être annulée. En cas de désaccord, ouvrez un litige.';
        }

        if ($mission->jalons()->where('statut', '!=', 'en_attente')->exists()) {
            return 'Une étape du chantier a déjà été soumise : cette mission ne peut plus être annulée. En cas de désaccord, ouvrez un litige.';
        }

        if ($mission->jcodes()->whereIn('statut', ['utilise', 'partiellement_utilise'])->exists()) {
            return 'Du matériel a déjà été retiré pour ce chantier : cette mission ne peut plus être annulée. En cas de désaccord, ouvrez un litige.';
        }

        return null;
    }

    /**
     * Séquestre de la mission, pénalité et remboursement, par portefeuille.
     * La pénalité est prélevée d'abord sur la main-d'œuvre, puis sur les matériaux.
     *
     * @return array{escrow: int, escrow_materiaux: int, escrow_mo: int, penalty_rate: float, penalty: int, penalty_materiaux: int, penalty_mo: int, refund: int, refund_materiaux: int, refund_mo: int}
     */
    private function amounts(Mission $mission): array
    {
        $materiaux = $this->walletService->getMissionEscrowBalance($mission, WalletType::WALLET_MATERIAUX);
        $mo = $this->walletService->getMissionEscrowBalance($mission, WalletType::WALLET_MO);
        $escrow = $materiaux + $mo;

        $rate = $this->penaltyRate();
        $penalty = min($escrow, (int) round($escrow * $rate / 100));
        $penaltyMo = min($mo, $penalty);
        $penaltyMateriaux = $penalty - $penaltyMo;

        return [
            'escrow' => $escrow,
            'escrow_materiaux' => $materiaux,
            'escrow_mo' => $mo,
            'penalty_rate' => $rate,
            'penalty' => $penalty,
            'penalty_materiaux' => $penaltyMateriaux,
            'penalty_mo' => $penaltyMo,
            'refund' => $escrow - $penalty,
            'refund_materiaux' => $materiaux - $penaltyMateriaux,
            'refund_mo' => $mo - $penaltyMo,
        ];
    }
}
