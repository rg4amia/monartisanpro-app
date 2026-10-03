<?php

use App\Services\ScoreService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 24 — la formule du Score ProsArtisan change (une étoile vaut
 * 0 point, plafond d'excellence sur le score total) : les scores stockés sont
 * recalculés, sans quoi classements et marqueur doré resteraient sur l'ancienne
 * formule jusqu'à la prochaine évaluation de chaque compte.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests() || ! Schema::hasTable('evaluations') || ! Schema::hasTable('score_ledger_entries')) {
            return;
        }

        try {
            Log::info('Scores recalculés après changement de formule', app(ScoreService::class)->recalculateAll());
        } catch (Throwable $e) {
            // Le déploiement ne doit pas échouer : chaque score se recalcule
            // de toute façon à sa prochaine lecture.
            Log::warning('Recalcul général des scores incomplet', ['message' => $e->getMessage()]);
        }
    }

    public function down(): void
    {
        // Sans objet : l'ancienne formule n'est pas restaurée.
    }
};
