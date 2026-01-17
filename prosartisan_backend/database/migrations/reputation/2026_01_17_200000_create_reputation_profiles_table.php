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
  Schema::create('reputation_profiles', function (Blueprint $table) {
   $table->uuid('id')->primary();
   $table->uuid('artisan_id');
   $table->integer('current_score')->default(0); // 0-100
   $table->float('reliability_score', 5, 2)->default(0); // Component scores
   $table->float('integrity_score', 5, 2)->default(100);
   $table->float('quality_score', 5, 2)->default(0);
   $table->float('reactivity_score', 5, 2)->default(0);
   $table->integer('completed_projects')->default(0);
   $table->integer('accepted_projects')->default(0);
   $table->float('average_rating', 3, 2)->default(0); // 0-5 stars
   $table->float('average_response_time_hours', 8, 2)->default(0);
   $table->integer('fraud_attempts')->default(0);
   $table->timestamp('last_calculated_at');
   $table->timestamps();

   // Foreign key constraint
   $table->foreign('artisan_id')->references('id')->on('users')->onDelete('cascade');

   // Indexes for performance
   $table->unique('artisan_id'); // One profile per artisan
   $table->index('current_score');
   $table->index('last_calculated_at');
   $table->index(['current_score', 'artisan_id']); // Composite index for top artisans
  });
 }

 /**
  * Reverse the migrations.
  */
 public function down(): void
 {
  Schema::dropIfExists('reputation_profiles');
 }
};
