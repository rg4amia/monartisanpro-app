<?php

namespace App\Console\Commands;

use App\Services\NotificationService;
use Illuminate\Console\Command;

/**
 * Purge des notifications lues depuis plus de 12 mois (Chantier 14, lot E,
 * décision du 29/09/2026). Une notification non lue n'est jamais purgée.
 * Planifiée chaque jour.
 */
class PurgeReadNotificationsCommand extends Command
{
    protected $signature = 'notifications:purge-read';

    protected $description = 'Supprime les notifications lues depuis plus longtemps que le délai de conservation.';

    public function handle(NotificationService $notifications): int
    {
        $this->info($notifications->purgeRead().' notification(s) lue(s) supprimée(s).');

        return self::SUCCESS;
    }
}
