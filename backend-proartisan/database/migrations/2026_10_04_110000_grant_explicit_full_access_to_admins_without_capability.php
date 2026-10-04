<?php

use App\Services\Admin\AdminPermissionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chantier 27, lot D — l'accès total devient un état écrit.
 *
 * Jusqu'ici un administrateur sans aucune capacité avait l'accès total, si
 * bien que « tout décocher » l'accordait. Ces comptes reçoivent la capacité
 * `admin.full-access` : leur accès ne change pas, il devient lisible. Les
 * administrateurs qui ont déjà des capacités ne sont pas touchés.
 * Idempotente (Règle d'or 55).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasTable('permissions') || ! Schema::hasTable('admin_permission_user')) {
            return;
        }

        $fullAccessId = DB::table('permissions')->where('name', AdminPermissionService::FULL_ACCESS)->value('id');

        if ($fullAccessId === null) {
            return;
        }

        $adminIds = DB::table('users')
            ->where('role', 'admin')
            ->whereNotIn('id', DB::table('admin_permission_user')->select('user_id'))
            ->pluck('id');

        foreach ($adminIds as $adminId) {
            DB::table('admin_permission_user')->insert([
                'user_id' => $adminId,
                'permission_id' => $fullAccessId,
                'created_at' => now(),
            ]);

            Cache::forget('admin_caps_user_'.$adminId);
        }
    }

    public function down(): void
    {
        // Sans retour : retirer ces lignes ne changerait pas l'accès de ces comptes.
    }
};
