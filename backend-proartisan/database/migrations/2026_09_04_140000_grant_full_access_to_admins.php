<?php

use App\Services\Admin\AdminPermissionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Chantier C6/C7 — attribue explicitement au super administrateur toutes les
 * capacités du backoffice via la sentinelle `admin.full-access` (déploiements
 * existants ; les nouveaux passent par PermissionSeeder).
 */
return new class extends Migration
{
    public function up(): void
    {
        $fullAccessId = DB::table('permissions')
            ->where('name', AdminPermissionService::FULL_ACCESS)
            ->value('id');

        if (! $fullAccessId) {
            return;
        }

        DB::table('users')->where('role', 'admin')->orderBy('id')->each(function ($user) use ($fullAccessId) {
            DB::table('admin_permission_user')->updateOrInsert(
                ['user_id' => $user->id, 'permission_id' => $fullAccessId],
                ['created_at' => now()],
            );
        });
    }

    public function down(): void
    {
        // Non réversible : on ne retire pas des droits d'administration.
    }
};
