<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::table('settings')->insert([
            'key' => 'commission_artisan_stock',
            'value' => '0.05',
            'type' => 'float',
            'group' => 'commissions',
            'label' => 'Commission sur Stock Artisan',
            'description' => 'Frais/Commission spécifique appliquée sur les matériaux issus du stock propre proposé par les artisans (en ratio, ex: 0.05 pour 5%).',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')->where('key', 'commission_artisan_stock')->delete();
    }
};
