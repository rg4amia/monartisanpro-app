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
  Schema::create('jalons', function (Blueprint $table) {
   $table->uuid('id')->primary();
   $table->uuid('chantier_id');
   $table->text('description');
   $table->bigInteger('labor_amount_centimes');
   $table->integer('sequence_number');
   $table->string('status', 50)->default('PENDING');

   // Proof of delivery fields
   $table->text('proof_photo_url')->nullable();
   $table->decimal('proof_latitude', 10, 8)->nullable();
   $table->decimal('proof_longitude', 11, 8)->nullable();
   $table->decimal('proof_accuracy', 8, 2)->nullable();
   $table->timestamp('proof_captured_at')->nullable();
   $table->json('proof_exif_data')->nullable();

   // Timestamps
   $table->timestamp('submitted_at')->nullable();
   $table->timestamp('validated_at')->nullable();
   $table->timestamp('auto_validation_deadline')->nullable();

   // Contest information
   $table->text('contest_reason')->nullable();

   $table->timestamps();

   // Foreign key constraints
   $table->foreign('chantier_id')->references('id')->on('chantiers')->onDelete('cascade');

   // Indexes for performance
   $table->index('chantier_id');
   $table->index('status');
   $table->index('sequence_number');
   $table->index('submitted_at');
   $table->index('validated_at');
   $table->index('auto_validation_deadline'); // Important for cron job
   $table->index('created_at');

   // Unique constraint - one jalon per sequence number per chantier
   $table->unique(['chantier_id', 'sequence_number']);
  });
 }

 /**
  * Reverse the migrations.
  */
 public function down(): void
 {
  Schema::dropIfExists('jalons');
 }
};
