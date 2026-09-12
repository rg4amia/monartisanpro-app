<?php

namespace App\Console\Commands;

use App\Models\KycDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Déplace les pièces KYC historiques du disque public vers le disque privé.
 *
 * Avant le durcissement, les CNI et selfies étaient écrits sur le disque
 * public : leur URL restait consultable indéfiniment, sans authentification,
 * par quiconque l'avait vue passer. Les nouveaux dépôts vont désormais sur le
 * disque privé, mais les dossiers déjà traités conservent leur ancienne URL —
 * c'est ce que cette commande corrige.
 *
 * Chaque document est traité indépendamment : un fichier manquant ou illisible
 * n'interrompt pas le lot, il est signalé et compté.
 */
class MigrateKycToPrivateDiskCommand extends Command
{
    protected $signature = 'kyc:migrate-to-private
                            {--dry-run : Montre ce qui serait déplacé sans rien modifier}
                            {--keep-source : Conserve le fichier public au lieu de le supprimer}';

    protected $description = 'Déplace les pièces KYC du disque public vers le disque privé et réécrit les références';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $keepSource = (bool) $this->option('keep-source');

        $documents = KycDocument::query()->orderBy('id')->get()
            ->filter(fn (KycDocument $doc) => $doc->isLegacy());

        if ($documents->isEmpty()) {
            $this->info('Aucune pièce KYC sur le disque public : rien à migrer.');

            return self::SUCCESS;
        }

        $this->info($documents->count().' pièce(s) à migrer'.($dryRun ? ' (simulation)' : '').'.');
        $this->newLine();

        $migrated = 0;
        $missing = 0;
        $failed = 0;

        foreach ($documents as $document) {
            $source = $this->publicRelativePath((string) $document->storagePath());

            if ($source === null) {
                $this->warn("#{$document->id} — référence non exploitable, ignorée.");
                $failed++;

                continue;
            }

            if (! Storage::disk('public')->exists($source)) {
                // Le fichier a déjà disparu du disque : la ligne reste, mais il
                // n'y a rien à déplacer. On la signale sans la réécrire, pour
                // ne pas créer une référence privée qui pointe dans le vide.
                $this->warn("#{$document->id} — fichier absent du disque public ({$source}).");
                $missing++;

                continue;
            }

            $target = 'kyc/'.basename($source);

            if ($dryRun) {
                $this->line("#{$document->id} — {$source} → {$target}");
                $migrated++;

                continue;
            }

            try {
                $stream = Storage::disk('public')->readStream($source);

                if ($stream === false || $stream === null) {
                    throw new \RuntimeException('flux de lecture indisponible');
                }

                // writeStream : les pièces peuvent peser plusieurs mégaoctets,
                // on évite de les charger entièrement en mémoire.
                Storage::disk('local')->writeStream($target, $stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }

                // La référence n'est réécrite qu'une fois la copie confirmée :
                // en cas d'échec, le document reste lisible à son ancienne URL.
                if (! Storage::disk('local')->exists($target)) {
                    throw new \RuntimeException('copie introuvable sur le disque privé');
                }

                $document->forceFill(['file_url' => $target])->save();

                if (! $keepSource) {
                    Storage::disk('public')->delete($source);
                }

                $this->line("#{$document->id} — migrée vers {$target}");
                $migrated++;
            } catch (Throwable $e) {
                $this->error("#{$document->id} — échec : ".$e->getMessage());
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Migrées : {$migrated}");

        if ($missing > 0) {
            $this->warn("Fichiers absents : {$missing}");
        }

        if ($failed > 0) {
            $this->error("Échecs : {$failed}");
        }

        if ($dryRun) {
            $this->newLine();
            $this->comment('Simulation : aucune modification enregistrée. Relancer sans --dry-run pour appliquer.');
        } elseif ($migrated > 0 && $keepSource) {
            $this->newLine();
            $this->warn('--keep-source : les copies publiques subsistent et restent accessibles. Les supprimer une fois la migration vérifiée.');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Ramène une référence historique au chemin relatif sur le disque public.
     *
     * Les valeurs rencontrées vont de l'URL absolue (`https://…/storage/x.jpg`)
     * au chemin déjà relatif, selon l'époque et l'environnement d'écriture.
     */
    private function publicRelativePath(string $raw): ?string
    {
        $path = $raw;

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            $parsed = parse_url($path, PHP_URL_PATH);

            if (! is_string($parsed) || $parsed === '') {
                return null;
            }

            $path = $parsed;
        }

        $path = ltrim($path, '/');

        if (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        $path = trim($path);

        // Une référence vide ou tentant de remonter l'arborescence est refusée.
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }

        return $path;
    }
}
