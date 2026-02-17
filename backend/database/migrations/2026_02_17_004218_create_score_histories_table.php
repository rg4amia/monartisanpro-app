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
        Schema::create('score_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artisan_id')->constrained('users')->onDelete('cascade');
            $table->decimal('total_score', 5, 2); // Snapshot at this point
            $table->decimal('reliability_score', 5, 2);
            $table->decimal('integrity_score', 5, 2);
            $table->decimal('quality_score', 5, 2);
            $table->decimal('responsiveness_score', 5, 2);
            $table->decimal('professionalism_score', 5, 2);
            $table->enum('event_type', ['project_completed', 'review_received', 'penalty', 'bonus', 'dispute_resolved']);
            $table->text('event_description')->nullable();
            $table->decimal('score_change', 6, 2)->default(0); // Can be negative
            $table->foreignId('triggered_by')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('project_id')->nullable()->constrained()->onDelete('set null');
            $table->timestamps();

            $table->index(['artisan_id', 'created_at']);
            $table->index('event_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('score_histories');
    }
};
