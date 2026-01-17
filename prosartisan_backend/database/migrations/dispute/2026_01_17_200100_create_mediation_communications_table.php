<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
 /**
  * Run the migrations.
  *
  * Creates the mediation_communications table for storing mediation messages
  *
  * Requirements: 9.5
  */
 public function up(): void
 {
  Schema::create('mediation_communications', function (Blueprint $table) {
   $table->id();
   $table->uuid('litige_id');
   $table->uuid('sender_id');
   $table->text('message');
   $table->timestamp('sent_at');
   $table->timestamps();

   // Foreign key constraints
   $table->foreign('litige_id')->references('id')->on('litiges')->onDelete('cascade');
   $table->foreign('sender_id')->references('id')->on('users')->onDelete('cascade');

   // Indexes for performance
   $table->index('litige_id');
   $table->index('sender_id');
   $table->index('sent_at');
   $table->index(['litige_id', 'sent_at']); // For chronological message retrieval
  });
 }

 /**
  * Reverse the migrations.
  */
 public function down(): void
 {
  Schema::dropIfExists('mediation_communications');
 }
};
