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
        Schema::create('ratings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('chantier_id');
            $table->uuid('rater_id'); // User who gives the rating
            $table->uuid('rated_id'); // User who receives the rating
            $table->integer('score'); // 1-5 stars
            $table->text('comment')->nullable();
            $table->timestamps();

            // Foreign key constraints
            $table->foreign('chantier_id')->references('id')->on('chantiers')->onDelete('cascade');
            $table->foreign('rater_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('rated_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes for performance
            $table->index('chantier_id');
            $table->index('rater_id');
            $table->index('rated_id');
            $table->index('score');
            $table->index('created_at');

            // Unique constraint - one rating per user per chantier
            $table->unique(['chantier_id', 'rater_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ratings');
    }
};
