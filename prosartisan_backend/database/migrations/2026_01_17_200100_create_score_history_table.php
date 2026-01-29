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
        Schema::create('score_history', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('reputation_profile_id');
            $table->integer('score');
            $table->string('change_reason', 255);
            $table->json('metrics_snapshot')->nullable();
            $table->timestamps();

            // Foreign key constraint
            $table->foreign('reputation_profile_id')->references('id')->on('reputation_profiles')->onDelete('cascade');

            // Indexes for performance
            $table->index('reputation_profile_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('score_history');
    }
};
