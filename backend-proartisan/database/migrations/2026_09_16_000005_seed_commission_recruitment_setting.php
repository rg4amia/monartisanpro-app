<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('settings')) {
            $exists = DB::table('settings')->where('key', 'commission_recruitment')->exists();
            if (! $exists) {
                DB::table('settings')->insert([
                    'key' => 'commission_recruitment',
                    'value' => '0.10',
                    'type' => 'float',
                    'group' => 'commissions',
                    'label' => 'Commission Recrutement BTP & Métiers',
                    'description' => "Pourcentage prélevé par ProsArtisan sur chaque journée de travail séquestrée puis libérée à l'artisan dans le module de recrutement (en ratio, ex: 0.10 pour 10%).",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('settings')) {
            DB::table('settings')->where('key', 'commission_recruitment')->delete();
        }
    }
};
