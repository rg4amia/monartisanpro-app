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
                'key' => 'app_access_disabled_message_client',
                'value' => 'L\'accès à l\'espace client est temporairement indisponible pour maintenance. Veuillez nous excuser pour la gêne occasionnée.',
                'type' => 'string',
                'group' => 'app_access',
                'label' => 'Message maintenance client',
                'description' => 'Message affiché lorsque l\'accès client est bloqué.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'app_access_disabled_message_artisan',
                'value' => 'L\'accès à l\'espace artisan est temporairement suspendu. Nos équipes interviennent rapidement. Merci de votre patience.',
                'type' => 'string',
                'group' => 'app_access',
                'label' => 'Message maintenance artisan',
                'description' => 'Message affiché lorsque l\'accès artisan est bloqué.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'app_access_disabled_message_fournisseur',
                'value' => 'L\'espace fournisseur est en cours de mise à jour technique. L\'accès sera rétabli sous peu.',
                'type' => 'string',
                'group' => 'app_access',
                'label' => 'Message maintenance fournisseur',
                'description' => 'Message affiché lorsque l\'accès fournisseur est bloqué.',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'app_access_disabled_message_livreur',
                'value' => 'L\'espace de livraison est momentanément inaccessible. Merci de réessayer d\'ici quelques instants.',
                'type' => 'string',
                'group' => 'app_access',
                'label' => 'Message maintenance livreur',
                'description' => 'Message affiché lorsque l\'accès livreur est bloqué.',
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
                'app_access_disabled_message_client',
                'app_access_disabled_message_artisan',
                'app_access_disabled_message_fournisseur',
                'app_access_disabled_message_livreur',
            ])->delete();
    }
};
