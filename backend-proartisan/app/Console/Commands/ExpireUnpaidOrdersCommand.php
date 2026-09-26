<?php

namespace App\Console\Commands;

use App\Services\OrderService;
use Illuminate\Console\Command;

/**
 * Annule les commandes de matériaux restées impayées au-delà de leur
 * échéance (Chantier 11) : l'opérateur est d'abord interrogé — un paiement
 * abouti sans webhook est encaissé, pas annulé —, puis stock et code promo
 * sont restitués.
 */
class ExpireUnpaidOrdersCommand extends Command
{
    protected $signature = 'prosartisan:expire-unpaid-orders';

    protected $description = 'Annule les commandes de matériaux non réglées dans le délai et remet leur stock en vente';

    public function handle(OrderService $orders): int
    {
        $result = $orders->expireUnpaidOrders();

        $this->info("Commandes échues examinées : {$result['checked']} — annulées : {$result['cancelled']}, finalement réglées : {$result['confirmed']}.");

        return self::SUCCESS;
    }
}
