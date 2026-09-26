<?php

namespace App\Console\Commands;

use App\Services\MobileMoneyPayoutService;
use Illuminate\Console\Command;

/**
 * Relance automatique des versements Mobile Money échoués arrivés à échéance
 * (délai croissant : 15 min, 30 min, 1 h, 2 h ; au-delà de
 * MAX_AUTOMATIC_ATTEMPTS tentatives, seule une relance humaine reprend).
 */
class RetryFailedPayoutsCommand extends Command
{
    protected $signature = 'prosartisan:retry-failed-payouts';

    protected $description = 'Relance les virements Mobile Money échoués (artisans, livreurs) arrivés à échéance';

    public function handle(MobileMoneyPayoutService $payouts): int
    {
        $result = $payouts->retryDue();

        $this->info("Versements relancés : {$result['retried']} — réussis : {$result['paid']}, toujours en échec : {$result['failed']}.");

        return self::SUCCESS;
    }
}
