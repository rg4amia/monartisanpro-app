<?php

namespace App\States\Mission;

/**
 * PENDING_APPROVAL — Artisan a déclaré un jalon terminé.
 * En attente de validation OTP du client, ou expiration 72h (Force-Pass CRON).
 * L'artisan ne peut plus soumettre de nouvelles dépenses sur ce jalon.
 */
class PendingApprovalState extends MissionState
{
    public static string $name = 'pending_approval';
}
