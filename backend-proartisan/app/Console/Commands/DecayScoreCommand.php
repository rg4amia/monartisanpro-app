<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ScoreService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * CRON Dégradation temporelle (« La Rouille ») du Score ProsArtisan.
 *
 * JUSTIFICATION BACKLOG (Epic 12) :
 * Si un artisan n'a validé aucun jalon et n'a accepté aucune mission
 * depuis ≥ 60 jours, son score se dégrade de −5 pts par semaine
 * d'inactivité supplémentaire.
 *
 * Schedule : quotidien (via routes/console.php).
 */
class DecayScoreCommand extends Command
{
    protected $signature = 'prosartisan:decay-score
                            {--dry-run : Affiche les artisans concernés sans appliquer la pénalité}';

    protected $description = 'Applique la dégradation temporelle (Rouille) sur les artisans inactifs ≥ 60 jours.';

    public function __construct(private ScoreService $scoreService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        $this->info('=== Dégradation Score ProsArtisan (inactivité ≥ 60j) ===');

        if ($isDryRun) {
            $this->warn('[DRY-RUN] Aucune modification ne sera effectuée.');
        }

        $artisans = User::query()
            ->where('role', 'artisan')
            ->where('account_status', 'actif')
            ->where('score_prosartisan', '>', 0)
            ->get();

        $penalized = 0;
        $skipped   = 0;

        foreach ($artisans as $artisan) {
            $inactivityDays = $this->scoreService->getInactivityDays($artisan);

            if ($inactivityDays < 60) {
                continue;
            }

            $label = "Artisan #{$artisan->id} ({$artisan->name}) — {$inactivityDays}j inactif, score actuel: {$artisan->score_prosartisan}";

            if ($isDryRun) {
                $this->line("  [DRY-RUN] Serait pénalisé : {$label}");
                $penalized++;
                continue;
            }

            try {
                $pointsRemoved = $this->scoreService->applyInactivityDecay($artisan);

                if ($pointsRemoved > 0) {
                    $newScore = $artisan->fresh()->score_prosartisan;
                    $this->info("  ✅ Pénalisé : {$label} → nouveau score: {$newScore} (−{$pointsRemoved} pts)");
                    $penalized++;
                } else {
                    $skipped++;
                }
            } catch (\Throwable $e) {
                $this->error("  ❌ Erreur sur {$label} : {$e->getMessage()}");
                Log::error('[DecayScore] Erreur', [
                    'user_id' => $artisan->id,
                    'error'   => $e->getMessage(),
                ]);
                $skipped++;
            }
        }

        $this->info("=== Résumé : {$penalized} pénalisés, {$skipped} ignorés ===");
        Log::info('[DecayScore] Traitement terminé', [
            'penalized' => $penalized,
            'skipped'   => $skipped,
        ]);

        return self::SUCCESS;
    }
}
