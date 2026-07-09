<?php

namespace App\States\Mission;

/**
 * COMPLETED — Validation finale et notation effectuée.
 * Le projet est figé en lecture seule (archive légale).
 * Aucun décaissement ni modification possible.
 */
class CompletedState extends MissionState
{
    public static string $name = 'terminee';
}
