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
     * Creates the artisan_profiles table for storing artisan-specific data
     * Uses PostGIS for location storage and spatial queries
     */
    public function up(): void
    {
        Schema::create('artisan_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('trade_category', 50); // PLUMBER, ELECTRICIAN, MASON
            $table->boolean('is_kyc_verified')->default(false);
            $table->json('kyc_documents')->nullable();

            // Location stored as separate latitude/longitude for MySQL compatibility
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            $table->timestamps();

            // Foreign key constraint
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            // Index on user_id for fast lookups
            $table->index('user_id');
            $table->index('trade_category');
            $table->index('is_kyc_verified');

            // Spatial index for location-based queries (with shorter name)
            $table->index(['latitude', 'longitude'], 'idx_artisan_location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('artisan_profiles');
    }
};
