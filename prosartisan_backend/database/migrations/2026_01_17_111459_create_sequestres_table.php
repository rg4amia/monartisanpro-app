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
        
        Schema::create('sequestres', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('mission_id');
            $table->uuid('client_id');
            $table->uuid('artisan_id');
            $table->bigInteger('total_amount_centimes');
            $table->bigInteger('materials_amount_centimes');
            $table->bigInteger('labor_amount_centimes');
            $table->bigInteger('materials_released_centimes')->default(0);
            $table->bigInteger('labor_released_centimes')->default(0);
            $table->string('status', 50)->default('BLOCKED');
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('mission_id')->references('id')->on('missions')->onDelete('cascade');
            $table->foreign('client_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('artisan_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes for performance
            $table->index('mission_id');
            $table->index('client_id');
            $table->index('artisan_id');
            $table->index('status');
            $table->index('created_at');

            // Unique constraint - one sequestre per mission
            $table->unique('mission_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sequestres');
    }
};
