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
  * Creates the fournisseur_profiles table for storing supplier-specific data
  * Uses PostGIS for shop location storage and spatial queries
  */
 public function up(): void
 {
  Schema::create('fournisseur_profiles', function (Blueprint $table) {
   $table->uuid('id')->primary();
   $table->uuid('user_id');
   $table->string('business_name', 255);
   $table->timestamps();

   // Foreign key constraint
   $table->foreign('user_id')
    ->references('id')
    ->on('users')
    ->onDelete('cascade');

   // Index on user_id for fast lookups
   $table->index('user_id');
  });

  // Add PostGIS geography column for shop location
  DB::statement('ALTER TABLE fournisseur_profiles ADD COLUMN shop_location GEOGRAPHY(POINT, 4326)');

  // Create spatial index on shop_location for efficient proximity queries
  DB::statement('CREATE INDEX idx_fournisseur_location ON fournisseur_profiles USING GIST(shop_location)');
 }

 /**
  * Reverse the migrations.
  */
 public function down(): void
 {
  Schema::dropIfExists('fournisseur_profiles');
 }
};
