<?php

namespace App\States\Mission\Transitions;

use App\Exceptions\MissionTransitionException;
use App\States\Mission\TransitionContext;
use Spatie\ModelStates\DefaultTransition;

class ToCompletedTransition extends DefaultTransition
{
    public function handle()
    {
        $mission = $this->model;

        // La clôture décidée par l'arbitrage d'un litige répartit elle-même
        // les fonds : elle n'attend pas les étapes restantes. Une décision qui
        // verse des fonds à l'artisan (en sa faveur ou partagée) reste soumise
        // au seuil Référent.
        $arbitrage = TransitionContext::flag('arbitrage');

        if ($arbitrage && ! in_array(TransitionContext::data()['decision'] ?? null, ['artisan', 'mixte'], true)) {
            return parent::handle();
        }

        $mission->loadMissing('jalons');

        // ── Guard 1 : Tous les jalons doivent être payés ou validés ──
        if (! $arbitrage && $mission->jalons->isNotEmpty()) {
            $unpaidJalons = $mission->jalons->filter(function ($jalon) {
                return ! in_array($jalon->statut, ['valide', 'paye'], true);
            });

            if ($unpaidJalons->isNotEmpty()) {
                throw new MissionTransitionException(
                    "Impossible de clôturer la mission #{$mission->id} : {$unpaidJalons->count()} étape(s) reste(nt) non validée(s) ou impayée(s)."
                );
            }
        }

        // ── Guard 2 : Règle d'or n° 5 (seuil Référent, lu dans la configuration) ──
        $seuil = (int) config('prosartisan.mission.referent_threshold', 2000000);

        if ((int) $mission->montant_total > $seuil && $mission->referent_validated_at === null) {
            throw new MissionTransitionException(
                'Clôture bloquée : les missions supérieures à '.number_format($seuil, 0, ',', ' ')
                ." FCFA requièrent la validation physique préalable du Référent de zone (Règle d'or n° 5)."
            );
        }

        return parent::handle();
    }
}
