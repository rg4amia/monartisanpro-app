<?php

namespace App\Console\Commands;

use App\Services\MissionService;
use Illuminate\Console\Command;

class AutoApproveMissionCompletionCommand extends Command
{
    protected $signature = 'missions:auto-approve-completion';

    protected $description = 'Clôture les missions restées sans validation finale du client au-delà du délai.';

    public function handle(MissionService $missions): int
    {
        $report = $missions->autoApproveCompletions();

        $this->info(sprintf(
            '%d mission(s) clôturée(s) après %d heures sans réponse du client.',
            $report['closed'],
            $missions->finalApprovalHours(),
        ));

        if ($report['blocked'] !== []) {
            $this->warn('Clôture refusée par une garde (seuil Référent…) : mission(s) #'.implode(', #', $report['blocked']));
        }

        return self::SUCCESS;
    }
}
