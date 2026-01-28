<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the referent_zone_profiles table for storing referent de zone data
     * Uses PostGIS for coverage area storage
     */
    public function up(): void
    {
        Schema::create('referent_zone_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('zone', 255); // Geographic zone name (e.g., "Abidjan Nord")

            // Coverage area stored as separate latitude/longitude for MySQL compatibility
            $table->decimal('coverage_latitude', 10, 8)->nullable();
            $table->decimal('coverage_longitude', 11, 8)->nullable();

            $table->timestamps();

            // Foreign key constraint
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            // Index on user_id for fast lookups
            $table->index('user_id');
            $table->index('zone');

            // Spatial index for location-based queries (with shorter name)
            $table->index(['coverage_latitude', 'coverage_longitude'], 'idx_referent_coverage');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referent_zone_profiles');
    }
};
