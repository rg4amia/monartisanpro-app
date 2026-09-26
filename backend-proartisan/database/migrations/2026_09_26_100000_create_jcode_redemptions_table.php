<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 7 — Le J-Code Multi-Comptoirs (Consommation fractionnée multi-quincailleries).
 *
 * Crée la table jcode_redemptions pour tracer chaque débit/rachat partiel d'un J-Code
 * chez chaque fournisseur agréé (montant servi, position GPS boutique, reçu photo, articles).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('jcode_redemptions')) {
            Schema::create('jcode_redemptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('jcode_id')->constrained('jcodes')->cascadeOnDelete();
                $table->foreignId('fournisseur_id')->constrained('users')->cascadeOnDelete();
                $table->bigInteger('montant'); // FCFA
                $table->string('recu_photo_url', 500)->nullable();
                $table->decimal('latitude', 10, 8)->nullable();
                $table->decimal('longitude', 11, 8)->nullable();
                $table->json('items_json')->nullable();
                $table->timestamp('scanned_at')->nullable();
                $table->timestamps();

                $table->index(['jcode_id', 'fournisseur_id']);
                $table->index('scanned_at');
            });

            if (config('database.default') !== 'sqlite') {
                DB::statement('ALTER TABLE jcode_redemptions ADD COLUMN position_scan POINT NULL AFTER longitude');
            }
        }

        // S'assurer que fournisseur_id peut être nullable sur jcodes pour les J-Codes multi-comptoirs
        if (config('database.default') !== 'sqlite') {
            DB::statement('ALTER TABLE jcodes MODIFY COLUMN fournisseur_id BIGINT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('jcode_redemptions');
    }
};
