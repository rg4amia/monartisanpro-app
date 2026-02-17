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
        Schema::create('artisan_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artisan_id')->unique()->constrained('users')->onDelete('cascade');
            $table->decimal('total_score', 5, 2)->default(0); // 0-100
            $table->decimal('reliability_score', 5, 2)->default(0); // 40% weight
            $table->decimal('integrity_score', 5, 2)->default(0); // 30% weight
            $table->decimal('quality_score', 5, 2)->default(0); // 15% weight
            $table->decimal('responsiveness_score', 5, 2)->default(0); // 10% weight
            $table->decimal('professionalism_score', 5, 2)->default(0); // 5% weight
            $table->integer('projects_completed')->default(0);
            $table->decimal('average_rating', 3, 2)->default(0); // 0-5.00
            $table->integer('total_reviews')->default(0);
            $table->enum('badge_level', ['bronze', 'silver', 'gold', 'none'])->default('none');
            $table->timestamp('last_calculated_at')->nullable();
            $table->timestamps();

            $table->index('artisan_id');
            $table->index(['badge_level', 'total_score']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('artisan_scores');
    }
};
