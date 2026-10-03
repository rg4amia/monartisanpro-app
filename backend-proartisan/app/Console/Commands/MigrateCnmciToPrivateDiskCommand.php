<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Déplace les cartes CNMCI du disque public vers le disque privé (Chantier 27).
 *
 * Avant ce chantier, la carte était écrite sur le disque public : son adresse
 * restait consultable indéfiniment, sans authentification. Chaque carte est
 * traitée indépendamment : un fichier absent ou illisible n'interrompt pas le
 * lot, il est signalé et compté.
 */
class MigrateCnmciToPrivateDiskCommand extends Command
{
    protected $signature = 'cnmci:migrate-to-private
                            {--dry-run : Montre ce qui serait déplacé sans rien modifier}';

    protected $description = 'Déplace les cartes CNMCI du disque public vers le disque privé et réécrit les références';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $rows = DB::table('users')
            ->whereNotNull('cnmci_card_url')
            ->where('cnmci_card_url', 'like', '/storage/%')
            ->orderBy('id')
            ->get(['id', 'cnmci_card_url']);

        if ($rows->isEmpty()) {
            $this->info('Aucune carte CNMCI sur le disque public : rien à migrer.');

            return self::SUCCESS;
        }

        $migrated = 0;
        $missing = 0;
        $failed = 0;

        foreach ($rows as $row) {
            $source = ltrim(substr((string) $row->cnmci_card_url, strlen('/storage/')), '/');

            if ($source === '' || str_contains($source, '..')) {
                $this->warn("Compte #{$row->id} — référence non exploitable, ignorée.");
                $failed++;

                continue;
            }

            if (! Storage::disk('public')->exists($source)) {
                $this->warn("Compte #{$row->id} — fichier absent du disque public ({$source}).");
                $missing++;

                continue;
            }

            $target = 'cnmci/'.basename($source);

            if ($dryRun) {
                $this->line("Compte #{$row->id} — {$source} → {$target}");
                $migrated++;

                continue;
            }

            try {
                Storage::disk('local')->put($target, Storage::disk('public')->get($source));

                // La référence n'est réécrite qu'une fois la copie confirmée.
                if (! Storage::disk('local')->exists($target)) {
                    throw new \RuntimeException('copie non confirmée');
                }

                DB::table('users')->where('id', $row->id)->update(['cnmci_card_url' => $target]);
                Storage::disk('public')->delete($source);
                $migrated++;
            } catch (Throwable $e) {
                $this->error("Compte #{$row->id} — échec : {$e->getMessage()}");
                $failed++;
            }
        }

        $this->info("Cartes déplacées : {$migrated} ; fichiers absents : {$missing} ; échecs : {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
