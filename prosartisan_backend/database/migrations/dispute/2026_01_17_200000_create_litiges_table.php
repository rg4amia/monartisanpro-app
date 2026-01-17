<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
 /**
  * Run the migrations.
  *
  * Creates the litiges table for dispute management
  *
  * Requirements: 9.1
  */
 public function up(): void
 {
  Schema::create('litiges', function (Blueprint $table) {
   $table->uuid('id')->primary();
   $table->uuid('mission_id');
   $table->uuid('reporter_id');
   $table->uuid('defendant_id');
   $table->string('type', 50); // QUALITY, PAYMENT, DELAY, OTHER
   $table->text('description');
   $table->json('evidence')->nullable(); // Array of evidence URLs
   $table->string('status', 50)->default('OPEN'); // OPEN, IN_MEDIATION, IN_ARBITRATION, RESOLVED, CLOSED

   // Mediation fields
   $table->uuid('mediator_id')->nullable();
   $table->timestamp('mediation_started_at')->nullable();
   $table->timestamp('mediation_ended_at')->nullable();

   // Arbitration fields
   $table->uuid('arbitrator_id')->nullable();
   $table->string('arbitration_decision_type', 50)->nullable(); // REFUND_CLIENT, PAY_ARTISAN, etc.
   $table->bigInteger('arbitration_decision_amount_centimes')->nullable();
   $table->text('arbitration_justification')->nullable();
   $table->timestamp('arbitration_rendered_at')->nullable();

   // Resolution fields
   $table->string('resolution_outcome')->nullable();
   $table->bigInteger('resolution_amount_centimes')->nullable();
   $table->text('resolution_notes')->nullable();

   $table->timestamp('resolved_at')->nullable();
   $table->timestamps();

   // Foreign key constraints
   $table->foreign('mission_id')->references('id')->on('missions')->onDelete('cascade');
   $table->foreign('reporter_id')->references('id')->on('users')->onDelete('cascade');
   $table->foreign('defendant_id')->references('id')->on('users')->onDelete('cascade');
   $table->foreign('mediator_id')->references('id')->on('users')->onDelete('set null');
   $table->foreign('arbitrator_id')->references('id')->on('users')->onDelete('set null');

   // Indexes for performance
   $table->index('mission_id');
   $table->index('reporter_id');
   $table->index('defendant_id');
   $table->index('status');
   $table->index('type');
   $table->index('created_at');
   $table->index(['reporter_id', 'defendant_id']); // For finding disputes involving users
  });
 }

 /**
  * Reverse the migrations.
  */
 public function down(): void
 {
  Schema::dropIfExists('litiges');
 }
};
