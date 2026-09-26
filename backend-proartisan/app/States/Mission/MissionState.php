<?php

namespace App\States\Mission;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * Classe abstraite parente de tous les états d'une Mission.
 * Définit les transitions autorisées globalement (machine à états).
 */
abstract class MissionState extends State
{
    /**
     * Libellés français des statuts de mission. La base stocke le nom technique
     * de l'état FSM (Règle d'Or 27) ; tout affichage passe par ces libellés —
     * identiques à `missionStatusLabels` du backoffice (resources/js/pages/admin/shared/constants.ts).
     */
    public const LABELS = [
        'draft' => 'Brouillon',
        'pending_artisan_acceptance' => "En attente d'acceptation artisan",
        'pending_funding' => 'En attente de financement',
        'funded_locked' => 'Financée (séquestre bloqué)',
        'in_progress' => 'En cours',
        'pending_approval' => 'En attente de validation client',
        'completed' => 'Terminée',
        'disputed' => 'En litige',
        'cancelled' => 'Annulée',
    ];

    public static function labelFor(string $status): string
    {
        return self::LABELS[$status] ?? $status;
    }

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(DraftState::class)

            // Transitions normales
            ->allowTransition(DraftState::class, PendingFundingState::class)
            ->allowTransition(DraftState::class, PendingArtisanAcceptanceState::class)
            ->allowTransition(PendingArtisanAcceptanceState::class, DraftState::class)
            ->allowTransition(PendingArtisanAcceptanceState::class, CancelledState::class)
            ->allowTransition(PendingFundingState::class, FundedLockedState::class, \App\States\Mission\Transitions\ToFundedLockedTransition::class)
            ->allowTransition(FundedLockedState::class, InProgressState::class, \App\States\Mission\Transitions\ToInProgressTransition::class)
            ->allowTransition(InProgressState::class, PendingApprovalState::class)
            ->allowTransition(PendingApprovalState::class, InProgressState::class, \App\States\Mission\Transitions\ToInProgressTransition::class)   // Nouveau jalon
            ->allowTransition(PendingApprovalState::class, CompletedState::class, \App\States\Mission\Transitions\ToCompletedTransition::class)
            ->allowTransition(InProgressState::class, CompletedState::class, \App\States\Mission\Transitions\ToCompletedTransition::class)

            // Transition d'urgence : tout état → DISPUTED
            ->allowTransition(PendingFundingState::class, DisputedState::class, \App\States\Mission\Transitions\ToDisputedTransition::class)
            ->allowTransition(FundedLockedState::class, DisputedState::class, \App\States\Mission\Transitions\ToDisputedTransition::class)
            ->allowTransition(InProgressState::class, DisputedState::class, \App\States\Mission\Transitions\ToDisputedTransition::class)
            ->allowTransition(PendingApprovalState::class, DisputedState::class, \App\States\Mission\Transitions\ToDisputedTransition::class)
            ->allowTransition(CompletedState::class, DisputedState::class, \App\States\Mission\Transitions\ToDisputedTransition::class)

            // Reprise après litige résolu
            ->allowTransition(DisputedState::class, InProgressState::class, \App\States\Mission\Transitions\ToInProgressTransition::class)
            ->allowTransition(DisputedState::class, CompletedState::class, \App\States\Mission\Transitions\ToCompletedTransition::class)
            ->allowTransition(DisputedState::class, CancelledState::class)

            // Transitions d'idempotence (soi-même)
            ->allowTransition(DraftState::class, DraftState::class)
            ->allowTransition(PendingArtisanAcceptanceState::class, PendingArtisanAcceptanceState::class)
            ->allowTransition(PendingFundingState::class, PendingFundingState::class)
            ->allowTransition(FundedLockedState::class, FundedLockedState::class)
            ->allowTransition(InProgressState::class, InProgressState::class)
            ->allowTransition(PendingApprovalState::class, PendingApprovalState::class)
            ->allowTransition(CompletedState::class, CompletedState::class)
            ->allowTransition(DisputedState::class, DisputedState::class)
            ->allowTransition(CancelledState::class, CancelledState::class);
    }
}
