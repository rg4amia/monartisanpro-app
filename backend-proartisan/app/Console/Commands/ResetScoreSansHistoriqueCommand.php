<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Corrige les comptes dont le Score ProsArtisan est non nul alors qu'ils
 * n'ont aucun historique (aucune évaluation reçue, aucune écriture au
 * ledger). Ces valeurs proviennent de données antérieures à la migration
 * `2026_08_14_154223_change_score_prosartisan_default_to_zero_on_users_table`
 * (défaut de colonne passé de 10 à 0, non rétroactif sur les lignes existantes).
 *
 * Règle d'or 14/15 : le Score ProsArtisan démarre à 0 et ne progresse que
 * via les notations, missions et livraisons — jamais de valeur préremplie.
 */
class ResetScoreSansHistoriqueCommand extends Command
{
    protected $signature = 'prosartisan:reset-score-sans-historique
                            {--dry-run : Affiche les comptes concernés sans appliquer la correction}';

    protected $description = 'Remet à 0 le Score ProsArtisan des comptes sans aucune évaluation ni écriture au ledger.';

    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');

        $this->info('=== Correction Score ProsArtisan sans historique ===');

        if ($isDryRun) {
            $this->warn('[DRY-RUN] Aucune modification ne sera effectuée.');
        }

        $candidats = User::query()
            ->where('score_prosartisan', '>', 0)
            ->get(['id', 'name', 'role', 'score_prosartisan', 'score_frozen']);

        $corriges = 0;
        $ignoresFroid = 0;
        $ignoresHistorique = 0;

        foreach ($candidats as $user) {
            if ($user->score_frozen) {
                $ignoresFroid++;

                continue;
            }

            $hasEvaluation = DB::table('evaluations')->where('evalue_id', $user->id)->exists();
            $hasLedgerEntry = DB::table('score_ledger_entries')->where('user_id', $user->id)->exists();

            if ($hasEvaluation || $hasLedgerEntry) {
                $ignoresHistorique++;

                continue;
            }

            $label = "#{$user->id} {$user->name} ({$user->role}) — score actuel: {$user->score_prosartisan}";

            if ($isDryRun) {
                $this->line("  [DRY-RUN] Serait remis à 0 : {$label}");
                $corriges++;

                continue;
            }

            $user->forceFill(['score_prosartisan' => 0])->save();
            $this->info("  ✅ Remis à 0 : {$label}");
            $corriges++;
        }

        $this->info("=== Résumé : {$corriges} corrigés, {$ignoresHistorique} conservés (historique réel), {$ignoresFroid} ignorés (score gelé) ===");
        Log::info('[ResetScoreSansHistorique] Traitement terminé', [
            'corriges' => $corriges,
            'ignores_historique' => $ignoresHistorique,
            'ignores_froid' => $ignoresFroid,
            'dry_run' => $isDryRun,
        ]);

        return self::SUCCESS;
    }
}
