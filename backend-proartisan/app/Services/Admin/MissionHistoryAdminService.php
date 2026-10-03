<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Services\MissionHistoryBackfillService;

/**
 * Backoffice — contrôle et reconstitution de l'historique des états des
 * missions (Règle d'or 89). La reconstitution n'ajoute que des lignes
 * manquantes, marquées comme reconstituées ; elle est auditée.
 */
class MissionHistoryAdminService
{
    public function __construct(
        private MissionHistoryBackfillService $backfill,
        private AdminActivityLogger $audit,
    ) {}

    /**
     * Missions financées ou clôturées dont l'historique est incomplet, sans rien écrire.
     *
     * @return array{missions: int, incomplete: int, lines: int}
     */
    public function check(): array
    {
        return $this->backfill->run(false);
    }

    /**
     * @return array{missions: int, incomplete: int, lines: int}
     */
    public function rebuild(User $actor): array
    {
        $report = $this->backfill->run(true);

        $this->audit->log('mission.history.rebuilt', null, $report, actor: $actor);

        return $report;
    }
}
