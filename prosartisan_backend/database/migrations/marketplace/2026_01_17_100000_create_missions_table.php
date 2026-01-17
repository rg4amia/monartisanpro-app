<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
 /**
  * Run the migrations.
  *
  * Creates the missions table for storing client work requests
  * Uses PostGIS for location storage and spatial queries
  */
 public function up(): void
 {
  Schema::create('missions', function (Blueprint $table) {
   $table->uuid('id')->primary();
   $table->uuid('client_id');
   $table->text('description');
   $table->string('trade_category', 50); // PLUMBER, ELECTRICIAN, MASON
   $table->bigInteger('budget_min_centimes');
   $table->bigInteger('budget_max_centimes');
   $table->string('status', 50)->default('OPEN'); // OPEN, QUOTED, ACCEPTED, CANCELLED
   $table->timestamps();

   // Foreign key constraint
   $table->foreign('client_id')
    ->references('id')
    ->on('users')
    ->onDelete('cascade');

   // Indexes for fast lookups
   $table->index('client_id');
   $table->index('status');
   $table->index('trade_category');
   $table->index('created_at');
  });

  // Add location column based on database driver
  if (DB::connection()->getDriverName() === 'sqlite') {
   // For SQLite (testing), use TEXT tostore JSON representation of coordinates
   DB::statement('ALTER TABLE missions ADD COLUMN location TEXT');
  } else {
   // For PostgreSQL (production), use PostGIS geography column
   DB::statement('ALTER TABLE missions ADD COLUMN location GEOGRAPHY(POINT, 4326) NOT NULL');
   // Create spatial index on location for efficient proximity queries
   DB::statement('CREATE INDEX idx_missions_location ON missions USING GIST(location)');
  }
 }

 /**
  * Reverse the migrations.
  */
 public function down(): void
 {
  Schema::dropIfExists('missions');
 }
};
