<?php

namespace App\States\Mission;

/**
 * PENDING_FUNDING — Client a accepté le devis, en attente de son paiement.
 * Guard : l'artisan ne peut pas générer de J-Code dans cet état.
 */
class PendingFundingState extends MissionState
{
    public static string $name = 'pending_funding';
}
