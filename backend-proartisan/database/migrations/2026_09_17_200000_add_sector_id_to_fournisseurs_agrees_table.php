<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Secteur d'activité du fournisseur (quincaillerie), réutilisant la même
 * taxonomie `sectors` que les métiers d'artisan. Sans cette colonne, aucune
 * répartition des fournisseurs par secteur n'est possible (Cartographie &
 * Territoires) : la donnée n'existait nulle part ailleurs (ni sur le
 * fournisseur, ni sur ses produits).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fournisseurs_agrees', function (Blueprint $table) {
            $table->foreignId('sector_id')->nullable()->after('nom_boutique')
                ->constrained('sectors')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fournisseurs_agrees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sector_id');
        });
    }
};
