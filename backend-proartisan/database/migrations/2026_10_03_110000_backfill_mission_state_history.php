<?php

use App\Services\MissionHistoryBackfillService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 19, lot 7 — reconstitue une fois, au déploiement, l'historique des
 * missions financées ou clôturées avant que tout changement d'état passe par
 * la machine à états. Idempotente : relancée, elle n'ajoute rien.
 *
 * Hors tests, comme les autres migrations de données : la suite construit
 * elle-même les missions dont elle a besoin.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests() || ! Schema::hasTable('mission_state_transitions')) {
            return;
        }

        $report = app(MissionHistoryBackfillService::class)->run(true);

        Log::info('Historique des missions reconstitué', $report);
    }

    public function down(): void
    {
        // Les lignes reconstituées sont conservées : elles documentent des faits réels.
    }
};
