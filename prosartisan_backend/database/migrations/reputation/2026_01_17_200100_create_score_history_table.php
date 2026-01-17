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
   $table->uuid('profile_id');
   $table->integer('score'); // 0-100
   $table->text('reason');
   $table->timestamp('recorded_at');
   $table->timestamps();

   // Foreign key constraint
   $table->foreign('profile_id')->references('id')->on('reputation_profiles')->onDelete('cascade');

   // Indexes for performance
   $table->index('profile_id');
   $table->index('recorded_at');
   $table->index(['profile_id', 'recorded_at']); // Composite index for history queries
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
