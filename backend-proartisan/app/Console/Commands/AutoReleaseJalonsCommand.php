<?php

namespace App\Console\Commands;

use App\Models\Jalon;
use App\Services\JalonService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * CRON Force-Pass 72h — Libération automatique des jalons bloqués.
 *
 * JUSTIFICATION BACKLOG (Epic 9) :
 * Si un client ne valide pas un jalon dans les 72h suivant sa soumission
 * (inaction ou blocage de mauvaise foi), le système libère automatiquement
 * les fonds vers le wallet de l'artisan.
 *
 * Guard : les missions en litige (DisputedState) sont ignorées.
 * Schedule : toutes les heures (via routes/console.php).
 */
class AutoReleaseJalonsCommand extends Command
{
    protected $signature = 'prosartisan:auto-release-jalons
                            {--dry-run : Affiche les jalons concernés sans les libérer}';

    protected $description = 'Libère automatiquement les jalons soumis sans validation client depuis 72h (Force-Pass).';

    public function __construct(private JalonService $jalonService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $delaiHeures = (int) config('prosartisan.jalon.force_release_delay_hours', 72);
        $cutoff      = now()->subHours($delaiHeures);
        $isDryRun    = $this->option('dry-run');

        $this->info("=== Force-Pass Jalons (délai : {$delaiHeures}h) ===");

        if ($isDryRun) {
            $this->warn('[DRY-RUN] Aucune modification ne sera effectuée.');
        }

        // Sélectionner les jalons soumis depuis plus de 72h
        // et dont la mission n'est PAS en litige (statut non-disputed)
        $jalons = Jalon::query()
            ->where('statut', 'soumis')
            ->where('updated_at', '<', $cutoff)
            ->whereHas('mission', fn ($q) => $q->where('status', '!=', 'disputed'))
            ->with(['mission.artisan', 'mission.client'])
            ->get();

        if ($jalons->isEmpty()) {
            $this->info('Aucun jalon à libérer.');
            Log::info('[AutoReleaseJalons] Aucun jalon éligible.');

            return self::SUCCESS;
        }

        $this->info("Jalons éligibles : {$jalons->count()}");

        $released = 0;
        $skipped  = 0;

        foreach ($jalons as $jalon) {
            $label = "Jalon #{$jalon->id} (Mission #{$jalon->mission_id}, Montant: {$jalon->montant} FCFA)";

            if ($isDryRun) {
                $this->line("  [DRY-RUN] Serait libéré : {$label}");
                continue;
            }

            try {
                $this->jalonService->forceRelease($jalon);
                $this->info("  ✅ Libéré : {$label}");
                $released++;
            } catch (\Throwable $e) {
                $this->error("  ❌ Erreur sur {$label} : {$e->getMessage()}");
                Log::error('[AutoReleaseJalons] Erreur de libération', [
                    'jalon_id'   => $jalon->id,
                    'mission_id' => $jalon->mission_id,
                    'error'      => $e->getMessage(),
                ]);
                $skipped++;
            }
        }

        if (! $isDryRun) {
            $this->info("=== Résumé : {$released} libérés, {$skipped} erreurs ===");
            Log::info('[AutoReleaseJalons] Traitement terminé', [
                'released' => $released,
                'skipped'  => $skipped,
                'delai_h'  => $delaiHeures,
            ]);
        }

        return self::SUCCESS;
    }
}
