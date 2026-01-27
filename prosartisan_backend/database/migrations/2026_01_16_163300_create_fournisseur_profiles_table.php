<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the fournisseur_profiles table for storing supplier-specific data
     * Uses PostGIS for shop location storage and spatial queries
     */
    public function up(): void
    {
        Schema::create('fournisseur_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('business_name', 255);

            // Shop location stored as separate latitude/longitude for MySQL compatibility
            $table->decimal('shop_latitude', 10, 8)->nullable();
            $table->decimal('shop_longitude', 11, 8)->nullable();

            $table->timestamps();

            // Foreign key constraint
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            // Index on user_id for fast lookups
            $table->index('user_id');

            // Spatial index for location-based queries
            $table->index(['shop_latitude', 'shop_longitude']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('fournisseur_profiles');
    }
};
