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
        // Skip PostGIS for SQLite (used in testing)
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

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
        // Skip PostGIS for SQLite (used in testing)
        if (DB::connection()->getDriverName() === 'sqlite') {
            return;
        }

        DB::statement('DROP EXTENSION IF EXISTS postgis_topology');
        DB::statement('DROP EXTENSION IF EXISTS postgis');
    }
};
