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
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(DraftState::class)

            // Transitions normales
            ->allowTransition(DraftState::class,          PendingFundingState::class)
            ->allowTransition(PendingFundingState::class, FundedLockedState::class)
            ->allowTransition(FundedLockedState::class,   InProgressState::class)
            ->allowTransition(InProgressState::class,     PendingApprovalState::class)
            ->allowTransition(PendingApprovalState::class, InProgressState::class)   // Nouveau jalon
            ->allowTransition(PendingApprovalState::class, CompletedState::class)

            // Transition d'urgence : tout état → DISPUTED
            ->allowTransition(PendingFundingState::class,  DisputedState::class)
            ->allowTransition(FundedLockedState::class,    DisputedState::class)
            ->allowTransition(InProgressState::class,      DisputedState::class)
            ->allowTransition(PendingApprovalState::class, DisputedState::class)

            // Reprise après litige résolu
            ->allowTransition(DisputedState::class, InProgressState::class)
            ->allowTransition(DisputedState::class, CompletedState::class)
            ->allowTransition(DisputedState::class, CancelledState::class)

            // Transitions d'idempotence (soi-même)
            ->allowTransition(DraftState::class,          DraftState::class)
            ->allowTransition(PendingFundingState::class,  PendingFundingState::class)
            ->allowTransition(FundedLockedState::class,    FundedLockedState::class)
            ->allowTransition(InProgressState::class,      InProgressState::class)
            ->allowTransition(PendingApprovalState::class, PendingApprovalState::class)
            ->allowTransition(CompletedState::class,      CompletedState::class)
            ->allowTransition(DisputedState::class,       DisputedState::class)
            ->allowTransition(CancelledState::class,      CancelledState::class);
    }
}
