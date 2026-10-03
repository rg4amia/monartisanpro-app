<?php

namespace App\Console\Commands;

use App\Services\MissionHistoryBackfillService;
use Illuminate\Console\Command;

class BackfillMissionStateHistoryCommand extends Command
{
    protected $signature = 'missions:backfill-state-history {--fix : Écrit les lignes manquantes (sans cette option, simple rapport)}';

    protected $description = "Reconstitue l'historique des états des missions financées ou clôturées avant le Chantier 19.";

    public function handle(MissionHistoryBackfillService $backfill): int
    {
        $write = (bool) $this->option('fix');
        $report = $backfill->run($write);

        $this->info(sprintf(
            '%d mission(s) examinée(s), %d à l\'historique incomplet, %d ligne(s) %s.',
            $report['missions'],
            $report['incomplete'],
            $report['lines'],
            $write ? 'reconstituée(s)' : 'à reconstituer (relancer avec --fix)',
        ));

        return self::SUCCESS;
    }
}
