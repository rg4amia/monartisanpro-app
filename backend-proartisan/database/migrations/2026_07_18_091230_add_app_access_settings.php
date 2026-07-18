<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('settings')->insert([
            [
                'key' => 'block_client',
                'value' => 'none',
                'type' => 'string',
                'group' => 'app_access',
                'label' => 'Blocage Accès Client',
                'description' => 'Détermine le blocage : none (aucun), new (nouveaux bloqués), old (anciens bloqués), all (tous bloqués)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'block_artisan',
                'value' => 'none',
                'type' => 'string',
                'group' => 'app_access',
                'label' => 'Blocage Accès Artisan',
                'description' => 'Détermine le blocage : none (aucun), new (nouveaux bloqués), old (anciens bloqués), all (tous bloqués)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'block_fournisseur',
                'value' => 'none',
                'type' => 'string',
                'group' => 'app_access',
                'label' => 'Blocage Accès Fournisseur',
                'description' => 'Détermine le blocage : none (aucun), new (nouveaux bloqués), old (anciens bloqués), all (tous bloqués)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'block_livreur',
                'value' => 'none',
                'type' => 'string',
                'group' => 'app_access',
                'label' => 'Blocage Accès Livreur',
                'description' => 'Détermine le blocage : none (aucun), new (nouveaux bloqués), old (anciens bloqués), all (tous bloqués)',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'app_access_disabled_message',
                'value' => 'L\'accès à cet espace est temporairement restreint suite à une opération de maintenance de nos services. Nous vous prions de nous excuser pour la gêne occasionnée et vous remercions de votre patience.',
                'type' => 'string',
                'group' => 'app_access',
                'label' => 'Message d\'erreur Accès',
                'description' => 'Message affiché lorsque l\'accès à l\'application est désactivé',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')
            ->whereIn('key', [
                'block_client',
                'block_artisan',
                'block_fournisseur',
                'block_livreur',
                'app_access_disabled_message',
            ])->delete();
    }
};
