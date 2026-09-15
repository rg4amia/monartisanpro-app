<?php

namespace App\Console\Commands;

use App\Services\RecruitmentService;
use Illuminate\Console\Command;

/**
 * Clôture automatique des offres de recrutement dont la date limite est
 * dépassée (`ACTIVE` → `EXPIRED`). Planifiée toutes les minutes.
 */
class ExpireRecruitmentOffersCommand extends Command
{
    protected $signature = 'recruitment:expire-offers';

    protected $description = 'Clôture automatiquement les offres de recrutement expirées.';

    public function __construct(private RecruitmentService $recruitment)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = $this->recruitment->expireOverdueOffers();

        if ($count > 0) {
            $this->info("{$count} offre(s) de recrutement expirée(s).");
        }

        return self::SUCCESS;
    }
}
