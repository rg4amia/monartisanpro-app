<?php

namespace App\Console\Commands;

use App\Services\LitigeService;
use Illuminate\Console\Command;

class ExpireJuryReviewsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jury:expire-overdue';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire les jurés ayant dépassé le délai imparti de 48h et assigne automatiquement des remplaçants.';

    /**
     * Execute the console command.
     */
    public function handle(LitigeService $litigeService): int
    {
        $this->info('Vérification des assignations de jury expirées (> 48h)...');

        $expiredCount = $litigeService->expireOverdueJuryReviews();

        if ($expiredCount > 0) {
            $this->warn("{$expiredCount} assignation(s) expirée(s) et remplacée(s) avec succès.");
        } else {
            $this->info('Aucune assignation expirée.');
        }

        return self::SUCCESS;
    }
}
