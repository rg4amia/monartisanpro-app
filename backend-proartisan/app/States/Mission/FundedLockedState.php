<?php

namespace App\States\Mission;

/**
 * FUNDED_LOCKED — Acompte reçu, séquestre actif.
 * Équivalent de l'ancien statut 'financee'.
 * Le client ne peut plus annuler sans pénalité.
 * L'artisan ne reçoit aucun cash.
 */
class FundedLockedState extends MissionState
{
    public static string $name = 'funded_locked';
}
