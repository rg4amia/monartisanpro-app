<?php

use App\Services\PdfService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Log;

/**
 * Chantier 38 — les rapports de solvabilité, reçus de paiement, factures de
 * décaissement et bordereaux de cash-out étaient écrits sur le disque public,
 * sous un nom devinable (identifiant et date à la seconde), et jamais
 * supprimés. Ils s'écrivent désormais sur le disque privé ; les copies restées
 * publiques sont retirées. Chaque document se régénère à la demande.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        try {
            $deleted = app(PdfService::class)->purgePublicCopies();
            Log::info("[Chantier 38] {$deleted} PDF retirés du disque public.");
        } catch (Throwable $e) {
            // Un échec de nettoyage ne bloque pas le déploiement : il se
            // rattrape à la main, et plus aucun document n'est écrit là.
            Log::warning('[Chantier 38] Nettoyage des PDF publics incomplet : '.$e->getMessage());
        }
    }

    public function down(): void
    {
        // Rien à rétablir : les documents se régénèrent à la demande.
    }
};
