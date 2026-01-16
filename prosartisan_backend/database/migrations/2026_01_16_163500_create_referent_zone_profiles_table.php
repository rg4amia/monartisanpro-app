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
  * Creates the referent_zone_profiles table for storing referent de zone data
  * Uses PostGIS for coverage area storage
  */
 public function up(): void
 {
  Schema::create('referent_zone_profiles', function (Blueprint $table) {
   $table->uuid('id')->primary();
   $table->uuid('user_id');
   $table->string('zone', 255); // Geographic zone name (e.g., "Abidjan Nord")
   $table->timestamps();

   // Foreign key constraint
   $table->foreign('user_id')
    ->references('id')
    ->on('users')
    ->onDelete('cascade');

   // Index on user_id for fast lookups
   $table->index('user_id');
   $table->index('zone');
  });

  // Add PostGIS geography column for coverage area
  DB::statement('ALTER TABLE referent_zone_profiles ADD COLUMN coverage_area GEOGRAPHY(POINT, 4326)');

  // Create spatial index on coverage_area
  DB::statement('CREATE INDEX idx_referent_coverage_area ON referent_zone_profiles USING GIST(coverage_area)');
 }

 /**
  * Reverse the migrations.
  */
 public function down(): void
 {
  Schema::dropIfExists('referent_zone_profiles');
 }
};
