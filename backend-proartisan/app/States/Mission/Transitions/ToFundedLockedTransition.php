<?php

namespace App\States\Mission\Transitions;

use Spatie\ModelStates\DefaultTransition;

class ToFundedLockedTransition extends DefaultTransition
{
    public function handle()
    {
        return parent::handle();
    }
}
