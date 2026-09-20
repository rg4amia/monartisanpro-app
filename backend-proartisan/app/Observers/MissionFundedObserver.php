<?php

namespace App\Observers;

use App\Models\Mission;
use App\Services\ParrainageClientService;

/**
 * Déclenche la récompense de parrainage client dès qu'une mission passe à
 * `funded_locked` (premier financement du filleul).
 */
class MissionFundedObserver
{
    public function updated(Mission $mission): void
    {
        if ($mission->isDirty('status') && (string) $mission->status === 'funded_locked') {
            app(ParrainageClientService::class)->recompenserSiEligible($mission->client);
        }
    }
}
