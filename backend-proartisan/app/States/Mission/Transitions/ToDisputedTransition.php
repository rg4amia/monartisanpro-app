<?php

namespace App\States\Mission\Transitions;

use App\Exceptions\MissionTransitionException;
use App\States\Mission\CancelledState;
use App\States\Mission\CompletedState;
use Spatie\ModelStates\DefaultTransition;

class ToDisputedTransition extends DefaultTransition
{
    public function handle()
    {
        $mission = $this->model;

        if ($mission->status instanceof CompletedState) {
            throw new MissionTransitionException('Impossible de placer en litige une mission déjà clôturée.');
        }

        if ($mission->status instanceof CancelledState) {
            throw new MissionTransitionException('Impossible de placer en litige une mission déjà annulée.');
        }

        // Verrouillage automatique des fonds sous séquestre
        $mission->funds_frozen = true;

        return parent::handle();
    }
}
