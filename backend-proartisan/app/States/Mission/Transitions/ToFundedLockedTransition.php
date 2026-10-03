<?php

namespace App\States\Mission\Transitions;

use App\Enums\PaymentStatus;
use App\Exceptions\MissionTransitionException;
use Spatie\ModelStates\DefaultTransition;

class ToFundedLockedTransition extends DefaultTransition
{
    public function handle()
    {
        $mission = $this->model;

        // ── Guard : pas de séquestre sans devis accepté ni paiement confirmé ──
        if (! $mission->devisAccepte()->exists()) {
            throw new MissionTransitionException(
                "Financement impossible : la mission #{$mission->id} n'a pas de devis accepté."
            );
        }

        if (! $mission->transactions()->where('statut', PaymentStatus::CONFIRME)->exists()) {
            throw new MissionTransitionException(
                "Financement impossible : aucun paiement confirmé pour la mission #{$mission->id}."
            );
        }

        return parent::handle();
    }
}
