<?php

namespace App\States\Mission;

/**
 * IN_PROGRESS — J-Code consommé ou 1re photo géolocalisée uploadée.
 * Équivalent de l'ancien statut 'en_cours'.
 * Le quincaillier ne peut plus annuler la transaction matérielle.
 */
class InProgressState extends MissionState
{
    public static string $name = 'in_progress';
}
