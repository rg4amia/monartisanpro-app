<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('devis', function (Blueprint $table) {
            // Ratio de fragmentation du séquestre (ex: 0.65 = 65% matériaux, 35% MO)
            // Ce ratio est IMMUABLE une fois le devis accepté
            $table->decimal('ratio_materiaux', 5, 4)->default(0.6500)->after('jalons_json')
                  ->comment('Ratio matériaux (0.0000 à 1.0000) - IMMUABLE après acceptation');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('devis', function (Blueprint $table) {
            $table->dropColumn('ratio_materiaux');
        });
    }
};
