<?php

use App\Models\Permission;
use App\Models\User;
use App\Services\Admin\AdminPermissionService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Assure l'existence et l'accès de l'administrateur principal avec prise en charge
 * de admin@prosartisan.com et admin@prosartisan.ci, et initialisation sécurisée du mot de passe.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (app()->environment('testing') || ! Schema::hasTable('users')) {
            return;
        }

        $fullAccessId = null;
        if (Schema::hasTable('permissions')) {
            $fullAccessPermission = DB::table('permissions')
                ->where('name', AdminPermissionService::FULL_ACCESS)
                ->first();
            $fullAccessId = $fullAccessPermission?->id;
        }

        // 1. Assurer que le compte principal existe et dispose d'un mot de passe
        $admin = User::where('email', 'admin@prosartisan.com')
            ->orWhere('email', 'admin@prosartisan.ci')
            ->orWhere('phone', '+2250000000000')
            ->first();

        if ($admin) {
            // Mettre à jour l'email et garantir les credentials
            if (! $admin->password) {
                $admin->password = Hash::make('admin123');
            }
            $admin->role = 'admin';
            $admin->kyc_status = 'actif';
            $admin->save();
        } else {
            $admin = User::create([
                'name' => 'Administrateur ProsArtisan',
                'email' => 'admin@prosartisan.com',
                'phone' => '+2250000000000',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
                'kyc_status' => 'actif',
            ]);
        }

        // 2. Assurer l'accès total
        if ($fullAccessId && Schema::hasTable('admin_permission_user')) {
            DB::table('admin_permission_user')->updateOrInsert(
                ['user_id' => $admin->id, 'permission_id' => $fullAccessId],
                ['created_at' => now()],
            );
            Cache::forget('admin_caps_user_'.$admin->id);
        }
    }

    public function down(): void
    {
        // Non réversible pour des raisons de sécurité
    }
};
