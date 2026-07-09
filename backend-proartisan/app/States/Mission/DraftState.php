<?php

namespace App\States\Mission;

/**
 * DRAFT — Devis en cours de négociation.
 * Aucun mouvement d'argent possible.
 */
class DraftState extends MissionState
{
    public static string $name = 'en_attente';
}
