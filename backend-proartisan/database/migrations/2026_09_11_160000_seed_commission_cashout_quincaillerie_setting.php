<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('settings')) {
            $exists = DB::table('settings')->where('key', 'commission_cashout_quincaillerie')->exists();
            if (!$exists) {
                DB::table('settings')->insert([
                    'key' => 'commission_cashout_quincaillerie',
                    'value' => '0.025',
                    'type' => 'float',
                    'group' => 'commissions',
                    'label' => 'Commission Retrait Cash Quincaillerie',
                    'description' => 'Pourcentage de commission alloué aux quincailleries partenaires pour les opérations de retrait d\'espèces à leur guichet (en ratio, ex: 0.025 pour 2.5%).',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('settings')) {
            DB::table('settings')->where('key', 'commission_cashout_quincaillerie')->delete();
        }
    }
};
