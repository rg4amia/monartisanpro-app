<?php

namespace App\States\Mission\Transitions;

use App\Models\Mission;
use DomainException;
use Spatie\ModelStates\DefaultTransition;

class ToCompletedTransition extends DefaultTransition
{
    public function handle()
    {
        $mission = $this->model;
        $mission->loadMissing('jalons');

        // ── Guard 1 : Tous les jalons doivent être payés ou validés ──
        if ($mission->jalons->isNotEmpty()) {
            $unpaidJalons = $mission->jalons->filter(function ($jalon) {
                return ! in_array($jalon->statut, ['valide', 'paye'], true);
            });

            if ($unpaidJalons->isNotEmpty()) {
                throw new DomainException(
                    "Impossible de clôturer la mission #{$mission->id} : {$unpaidJalons->count()} jalon(s) reste(nt) non validé(s) ou impayé(s)."
                );
            }
        }

        // ── Guard 2 : Règle d'or n° 5 (Seuil Référent > 2 000 000 FCFA) ──
        if ((int) $mission->montant_total > 2000000 && $mission->referent_validated_at === null) {
            throw new DomainException(
                "Clôture bloquée : les missions supérieures à 2 000 000 FCFA requièrent obligatoirement la validation physique préalable du Référent de zone (Règle d'or n° 5)."
            );
        }

        return parent::handle();
    }
}
