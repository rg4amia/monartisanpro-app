<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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
