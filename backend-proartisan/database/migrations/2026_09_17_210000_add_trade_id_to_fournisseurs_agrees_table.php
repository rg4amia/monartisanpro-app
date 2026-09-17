<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sous-catégorie (métier) du fournisseur au sein de son secteur d'activité,
 * même principe que `sector_id` : réutilise la taxonomie `trades` déjà en
 * place pour les artisans plutôt que d'inventer un nouveau référentiel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fournisseurs_agrees', function (Blueprint $table) {
            $table->foreignId('trade_id')->nullable()->after('sector_id')
                ->constrained('trades')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fournisseurs_agrees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('trade_id');
        });
    }
};
