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
   $table->uuid('artisan_id');
   $table->integer('old_score');
   $table->integer('new_score');
   $table->text('reason');
   $table->timestamp('recorded_at');

   $table->foreign('artisan_id')->references('id')->on('users')->onDelete('cascade');
   $table->index(['artisan_id', 'recorded_at']);
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
