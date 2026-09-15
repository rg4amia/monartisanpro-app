<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute la date de début de mission, distincte de `deadline_at` (date de fin /
 * date limite) déjà utilisée par la clôture automatique des offres.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_offers', function (Blueprint $table) {
            $table->date('date_debut')->nullable()->after('sous_quartier');
        });
    }

    public function down(): void
    {
        Schema::table('recruitment_offers', function (Blueprint $table) {
            $table->dropColumn('date_debut');
        });
    }
};
