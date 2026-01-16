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
  * Creates the artisan_profiles table for storing artisan-specific data
  * Uses PostGIS for location storage and spatial queries
  */
 public function up(): void
 {
  Schema::create('artisan_profiles', function (Blueprint $table) {
   $table->uuid('id')->primary();
   $table->uuid('user_id');
   $table->string('trade_category', 50); // PLUMBER, ELECTRICIAN, MASON
   $table->boolean('is_kyc_verified')->default(false);
   $table->jsonb('kyc_documents')->nullable();
   $table->timestamps();

   // Foreign key constraint
   $table->foreign('user_id')
    ->references('id')
    ->on('users')
    ->onDelete('cascade');

   // Index on user_id for fast lookups
   $table->index('user_id');
   $table->index('trade_category');
   $table->index('is_kyc_verified');
  });

  // Add location column based on database driver
  if (DB::connection()->getDriverName() === 'sqlite') {
   // For SQLite (testing), use TEXT to store JSON representation of coordinates
   DB::statement('ALTER TABLE artisan_profiles ADD COLUMN location TEXT');
  } else {
   // For PostgreSQL (production), use PostGIS geography column
   DB::statement('ALTER TABLE artisan_profiles ADD COLUMN location GEOGRAPHY(POINT, 4326)');
   // Create spatial index on location for efficient proximity queries
   DB::statement('CREATE INDEX idx_artisan_location ON artisan_profiles USING GIST(location)');
  }
 }

 /**
  * Reverse the migrations.
  */
 public function down(): void
 {
  Schema::dropIfExists('artisan_profiles');
 }
};
