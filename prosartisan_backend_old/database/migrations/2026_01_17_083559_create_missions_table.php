<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('missions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('client_id');
            $table->text('description');
            $table->string('trade_category', 50); // PLUMBER, ELECTRICIAN, MASON
            $table->bigInteger('budget_min_centimes');
            $table->bigInteger('budget_max_centimes');
            $table->string('status', 50)->default('OPEN'); // OPEN, QUOTED, ACCEPTED, CANCELLED

            // Location stored as separate latitude/longitude for MySQL compatibility
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();

            $table->timestamps();

            // Foreign key constraint
            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes for performance
            $table->index('client_id');
            $table->index('status');
            $table->index('trade_category');
            $table->index('created_at');

            // Spatial index for location-based queries (with shorter name)
            $table->index(['latitude', 'longitude'], 'idx_mission_location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('missions');
    }
};
