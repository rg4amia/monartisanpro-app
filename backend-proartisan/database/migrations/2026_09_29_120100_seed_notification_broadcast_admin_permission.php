<?php

use App\Services\Admin\AdminPermissionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Capacité `admin.notifications.broadcast` : campagnes push et SMS du
 * backoffice (Chantier 14, lot D). Recopie idempotente du catalogue des
 * capacités.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

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
        DB::table('permissions')->where('name', 'admin.notifications.broadcast')->delete();
    }
};
