<?php

use App\Models\Permission;
use App\Models\User;
use App\Services\Admin\AdminPermissionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rétablit l'accès total pour tous les administrateurs et élimine tout
 * auto-verrouillage accidentel survenu suite à une restriction partielle.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('admin_permission_user')) {
            return;
        }

        $fullAccessPermission = DB::table('permissions')
            ->where('name', AdminPermissionService::FULL_ACCESS)
            ->first();

        if (! $fullAccessPermission) {
            $fullAccessId = DB::table('permissions')->insertGetId([
                'name' => AdminPermissionService::FULL_ACCESS,
                'description' => 'Accès total à l\'ensemble des capacités du backoffice',
                'category' => 'admin:root',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $fullAccessId = $fullAccessPermission->id;
        }

        $admins = User::where('role', 'admin')->get();

        foreach ($admins as $admin) {
            // Réinitialiser les restrictions pour garantir l'accès total
            DB::table('admin_permission_user')->where('user_id', $admin->id)->delete();

            DB::table('admin_permission_user')->insert([
                'user_id' => $admin->id,
                'permission_id' => $fullAccessId,
                'created_at' => now(),
            ]);

            Cache::forget('admin_caps_user_'.$admin->id);
        }
    }

    public function down(): void
    {
        // Non réversible pour des raisons de sécurité
    }
};
