<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->foreignId('intervention_type_id')->nullable()->after('requested_trade_id')->constrained('intervention_types')->nullOnDelete();
        });

        // Le client doit pouvoir demander un simple déplacement/diagnostic avant
        // que l'artisan ne chiffre le devis complet avec matériaux (avenant).
        DB::table('intervention_types')->insertOrIgnore([
            'name' => 'Déplacement / Diagnostic',
            'requires_labor' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('missions', function (Blueprint $table) {
            $table->dropForeign(['intervention_type_id']);
            $table->dropColumn('intervention_type_id');
        });
    }
};
