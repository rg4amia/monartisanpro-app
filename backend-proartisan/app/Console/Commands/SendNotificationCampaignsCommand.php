<?php

namespace App\Console\Commands;

use App\Services\Notifications\NotificationCampaignService;
use Illuminate\Console\Command;

/**
 * Envoi des campagnes push et SMS du backoffice (Chantier 14, lot D) :
 * démarre les campagnes arrivées à échéance et sert un lot de destinataires
 * par campagne en cours. Planifiée toutes les minutes.
 */
class SendNotificationCampaignsCommand extends Command
{
    protected $signature = 'notifications:send-campaigns';

    protected $description = 'Envoie par lots les campagnes push et SMS programmées depuis le backoffice.';

    public function handle(NotificationCampaignService $campaigns): int
    {
        foreach ($campaigns->processDue() as $id => $served) {
            if ($served > 0) {
                $this->info("Campagne #{$id} : {$served} destinataire(s) servi(s).");
            }
        }

        return self::SUCCESS;
    }
}
