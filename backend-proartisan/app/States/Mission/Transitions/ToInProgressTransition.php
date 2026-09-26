<?php

namespace App\States\Mission\Transitions;

use DomainException;
use Spatie\ModelStates\DefaultTransition;

class ToInProgressTransition extends DefaultTransition
{
    public function handle()
    {
        $mission = $this->model;

        if ($mission->funds_frozen) {
            throw new DomainException("Impossible de passer la mission en cours : les fonds sont actuellement gelés.");
        }

        return parent::handle();
    }
}
