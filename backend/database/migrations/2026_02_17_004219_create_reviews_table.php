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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->onDelete('cascade');
            $table->foreignId('artisan_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('client_id')->constrained('users')->onDelete('cascade');
            $table->integer('rating'); // 1-5 overall
            $table->text('comment')->nullable();
            $table->integer('quality_rating')->default(5);
            $table->integer('communication_rating')->default(5);
            $table->integer('timeliness_rating')->default(5);
            $table->integer('professionalism_rating')->default(5);
            $table->boolean('would_recommend')->default(true);
            $table->json('photos')->nullable(); // Array of photo URLs
            $table->text('artisan_response')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->unique('project_id'); // One review per project
            $table->index(['artisan_id', 'rating']);
            $table->index('client_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
