<?php

namespace App\States\Mission;

/**
 * DISPUTED — Mode "Guerre". Un litige est déclaré.
 * Tout est gelé. Plus aucun décaissement automatisé n'est possible.
 * Seul l'Admin peut forcer une transition depuis cet état.
 */
class DisputedState extends MissionState
{
    public static string $name = 'litige';
}
