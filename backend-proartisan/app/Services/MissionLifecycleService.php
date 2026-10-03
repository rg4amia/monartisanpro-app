<?php

namespace App\Services;

use App\Exceptions\MissionTransitionException;
use App\Models\Mission;
use App\Models\User;
use App\States\Mission\MissionState;
use App\States\Mission\TransitionContext;
use Illuminate\Support\Facades\DB;
use Spatie\ModelStates\Exceptions\CouldNotPerformTransition;

/**
 * Point d'entrée unique des changements d'état d'une mission (Chantier 19).
 *
 * Écrire la colonne `status` directement sautait les gardes de la machine à
 * états et n'inscrivait rien dans l'historique : le financement, le litige,
 * la clôture et l'annulation n'y figuraient pas. Toute transition passe donc
 * par ici ; `MissionStatusWriteGuardTest` refuse les écritures directes.
 *
 * Ce service ne dépend d'aucun autre : portefeuille, devis, étapes et litiges
 * peuvent tous l'appeler sans dépendance circulaire.
 */
class MissionLifecycleService
{
    /**
     * @param  class-string<MissionState>  $state
     * @param  User|null  $actor  Auteur du changement ; nul pour une action du système (commande planifiée, paiement confirmé).
     * @param  array<string, mixed>  $context  Précisions consignées dans l'historique et lues par les gardes.
     *
     * @throws MissionTransitionException
     */
    public function transition(Mission $mission, string $state, ?User $actor = null, ?string $reason = null, array $context = []): Mission
    {
        if ($mission->status instanceof $state) {
            return $mission;
        }

        $from = (string) $mission->status;

        return DB::transaction(function () use ($mission, $state, $actor, $reason, $context, $from) {
            TransitionContext::open($actor, $reason, $context);

            try {
                $mission->status->transitionTo($state);
            } catch (CouldNotPerformTransition $e) {
                throw new MissionTransitionException(sprintf(
                    'Une mission « %s » ne peut pas passer à « %s ».',
                    MissionState::labelFor($from),
                    MissionState::labelFor($state::$name),
                ), previous: $e);
            } finally {
                TransitionContext::close();
            }

            return $mission;
        });
    }

    /**
     * Enchaîne plusieurs transitions, chacune inscrite dans l'historique.
     *
     * @param  list<class-string<MissionState>>  $states
     * @param  array<string, mixed>  $context
     */
    public function transitionThrough(Mission $mission, array $states, ?User $actor = null, ?string $reason = null, array $context = []): Mission
    {
        foreach ($states as $state) {
            $this->transition($mission, $state, $actor, $reason, $context);
        }

        return $mission;
    }
}
