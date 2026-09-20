<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Parrainage client → client. Distinct de la table `parrainages` (parrainage
 * artisan → artisan, hors périmètre). Un filleul n'a jamais qu'un seul
 * parrain (`filleul_id` unique).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parrainages_clients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parrain_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('filleul_id')->unique()->constrained('users')->onDelete('cascade');
            $table->foreignId('campagne_id')->nullable()->constrained('campagnes_parrainage')->nullOnDelete();
            $table->foreignId('promo_code_id')->nullable()->constrained('promo_codes')->nullOnDelete();
            $table->enum('statut', ['en_attente', 'recompense'])->default('en_attente');
            $table->timestamp('recompense_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parrainages_clients');
    }
};
