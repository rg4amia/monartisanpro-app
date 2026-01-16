<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
 /**
  * Run the migrations.
  */
 public function up(): void
 {
  // Enable PostGIS extension for geospatial queries
  DB::statement('CREATE EXTENSION IF NOT EXISTS postgis');

  // Enable PostGIS topology extension (optional but useful)
  DB::statement('CREATE EXTENSION IF NOT EXISTS postgis_topology');
 }

 /**
  * Reverse the migrations.
  */
 public function down(): void
 {
  DB::statement('DROP EXTENSION IF EXISTS postgis_topology');
  DB::statement('DROP EXTENSION IF EXISTS postgis');
 }
};
