<?php

namespace App\Console\Commands;

use App\Services\DeliveryFareCollectionService;
use Illuminate\Console\Command;

/**
 * Relance les clients dont la course livrée reste impayée (push + SMS) et
 * restreint ceux qui dépassent le plafond de relances (Chantier 11).
 */
class RemindUnpaidDeliveryFaresCommand extends Command
{
    protected $signature = 'prosartisan:remind-unpaid-delivery-fares';

    protected $description = 'Relance les courses livrées impayées et restreint les clients au-delà du plafond de relances';

    public function handle(DeliveryFareCollectionService $collection): int
    {
        $result = $collection->processDue();

        $this->info("Relances envoyées : {$result['reminded']} — comptes restreints : {$result['restricted']}.");

        return self::SUCCESS;
    }
}
