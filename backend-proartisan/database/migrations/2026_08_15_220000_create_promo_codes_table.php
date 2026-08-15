<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('promo_codes')) {
            Schema::create('promo_codes', function (Blueprint $table) {
                $table->id();
                $table->string('code', 50)->unique();
                $table->string('description')->nullable();
                $table->enum('discount_type', ['percent', 'fixed'])->default('percent');
                $table->unsignedInteger('discount_value')->default(10); // ex: 10% ou 1000 FCFA
                $table->unsignedBigInteger('min_order_amount')->default(0); // Montant min d'achat en FCFA
                $table->unsignedBigInteger('max_discount_amount')->nullable(); // Plafond max en FCFA
                $table->unsignedInteger('usage_limit')->nullable(); // Nombre max d'utilisations
                $table->unsignedInteger('used_count')->default(0);
                $table->timestamp('starts_at')->nullable();
                $table->timestamp('expires_at')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });

            // Code promo par défaut PROS225
            DB::table('promo_codes')->insertOrIgnore([
                'code' => 'PROS225',
                'description' => 'Offre de bienvenue : -10% sur les commandes matériaux et articles',
                'discount_type' => 'percent',
                'discount_value' => 10,
                'min_order_amount' => 0,
                'max_discount_amount' => 50000,
                'usage_limit' => 10000,
                'used_count' => 0,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};
