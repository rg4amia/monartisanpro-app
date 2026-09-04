<?php

use App\Services\Admin\AdminPermissionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Chantier C7 — capacité `admin.users.impersonate` (usurpation de session).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (AdminPermissionService::catalog() as $group => $capabilities) {
            foreach ($capabilities as $name => $description) {
                DB::table('permissions')->updateOrInsert(
                    ['name' => $name],
                    [
                        'description' => $description,
                        'category' => 'admin:'.$group,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ],
                );
            }
        }
    }

    public function down(): void
    {
        DB::table('permissions')->where('name', 'admin.users.impersonate')->delete();
    }
};
