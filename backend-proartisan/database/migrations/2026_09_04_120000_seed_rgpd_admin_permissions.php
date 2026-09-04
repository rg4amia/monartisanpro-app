<?php

use App\Services\Admin\AdminPermissionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Chantier C6 (P2-11) — enregistre les capacités RGPD (et toute capacité
 * « admin.* » du catalogue absente de la table `permissions`).
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
        DB::table('permissions')->whereIn('name', ['admin.rgpd.view', 'admin.rgpd.manage'])->delete();
    }
};
