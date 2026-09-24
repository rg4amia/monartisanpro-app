<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Ajoute la capacité de forcer une transition administrative de mission.
 */
return new class extends Migration
{
    private const PERMISSION = 'admin.missions.manage';

    public function up(): void
    {
        DB::table('permissions')->updateOrInsert(
            ['name' => self::PERMISSION],
            [
                'description' => 'Forcer une transition administrative de mission',
                'category' => 'admin:missions',
                'updated_at' => now(),
                'created_at' => now(),
            ],
        );
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', self::PERMISSION)->delete();
    }
};
