<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Campagnes de parrainage client → client (distinct du parrainage artisan
 * existant, table `parrainages`). Pilotées depuis le backoffice : périodes,
 * taux de remise appliqués au code promo généré à la récompense.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campagnes_parrainage', function (Blueprint $table) {
            $table->id();
            $table->string('libelle');
            $table->enum('discount_type', ['percent', 'fixed'])->default('percent');
            $table->unsignedInteger('discount_value')->default(10);
            $table->unsignedBigInteger('max_discount_amount')->nullable();
            $table->unsignedBigInteger('min_montant')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campagnes_parrainage');
    }
};
