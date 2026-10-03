<?php

namespace App\Console\Commands;

use App\Services\MissionService;
use Illuminate\Console\Command;

class ExpireArtisanRequestsCommand extends Command
{
    protected $signature = 'missions:expire-artisan-requests';

    protected $description = "Relance l'artisan à mi-délai, puis lui retire les demandes de devis restées sans réponse.";

    public function handle(MissionService $missions): int
    {
        $report = $missions->processUnansweredRequests();

        $this->info(sprintf(
            '%d artisan(s) relancé(s), %d demande(s) retirée(s) après %d heures sans réponse.',
            $report['reminded'],
            $report['expired'],
            $missions->artisanResponseHours(),
        ));

        return self::SUCCESS;
    }
}
