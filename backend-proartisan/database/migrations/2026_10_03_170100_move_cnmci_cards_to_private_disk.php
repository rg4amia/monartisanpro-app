<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 27 — déplace au déploiement les cartes CNMCI encore servies par une
 * adresse publique. Un échec n'interrompt pas le déploiement : la commande
 * `cnmci:migrate-to-private` se relance à la main. Sans effet pendant les tests.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->runningUnitTests() || ! Schema::hasColumn('users', 'cnmci_card_url')) {
            return;
        }

        try {
            Artisan::call('cnmci:migrate-to-private');
            Log::info('[Chantier 27] Cartes CNMCI : '.trim(Artisan::output()));
        } catch (Throwable $e) {
            Log::error('[Chantier 27] Déplacement des cartes CNMCI non effectué : '.$e->getMessage());
        }
    }

    public function down(): void
    {
        // Les cartes ne reviennent pas sur le disque public.
    }
};
