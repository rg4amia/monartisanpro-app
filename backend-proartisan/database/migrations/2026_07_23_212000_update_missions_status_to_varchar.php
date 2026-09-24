<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("
                ALTER TABLE missions
                MODIFY COLUMN status VARCHAR(50) NOT NULL DEFAULT 'draft'
            ");
        }
    }

    public function down(): void
    {
        // Pas de réversion nécessaire
    }
};
