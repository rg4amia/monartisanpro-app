<?php

namespace App\Console\Commands;

use App\Services\SupplierCashoutService;
use Illuminate\Console\Command;

class ProcessSupplierCashoutsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'prosartisan:process-supplier-payouts-j1';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Traite et décaisse automatiquement les demandes de cash-out quincaillerie éligibles à J+1 garanti';

    /**
     * Execute the console command.
     */
    public function handle(SupplierCashoutService $cashoutService): int
    {
        $this->info('Lancement du traitement automatique des virements quincaillerie J+1...');

        $result = $cashoutService->processAutomatedPayoutsJ1();

        $this->line("Total demandes éligibles (> 24h & KYC actif) : {$result['total_eligible']}");
        $this->info("Demandes traitées et décaissées avec succès : {$result['processed_count']}");

        if ($result['failed_count'] > 0) {
            $this->warn("Demandes en échec : {$result['failed_count']}");
            foreach ($result['failed'] as $fail) {
                $this->error("- {$fail['reference']} : {$fail['error']}");
            }
        }

        return self::SUCCESS;
    }
}
