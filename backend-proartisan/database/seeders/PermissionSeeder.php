<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\User;
use App\Services\RolePermissionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Catalogue des actions (liste de référence :
        // RolePermissionService::CATALOG). La base en garde une copie.
        foreach (RolePermissionService::CATALOG as $name => [$category, $description]) {
            Permission::updateOrCreate(['name' => $name], ['category' => $category, 'description' => $description]);
        }

        // 2. Droits d'origine par rôle (liste de référence :
        // RolePermissionService::DEFAULTS). Le seeder n'écrit que pour un
        // rôle qui n'a encore aucun droit : relancé, il n'efface jamais les
        // réglages faits dans l'écran « Rôles & Actions ».
        foreach (RolePermissionService::DEFAULTS as $role => $permNames) {
            if (DB::table('permission_role')->where('role', $role)->exists()) {
                continue;
            }

            foreach (Permission::whereIn('name', $permNames)->pluck('id') as $permId) {
                DB::table('permission_role')->insert([
                    'permission_id' => $permId,
                    'role' => $role,
                    'created_at' => now(),
                ]);
            }
        }

        $this->grantFullAccessToAdmins();
    }

    /**
     * Chantier C6/C7 — le super administrateur dispose de toutes les capacités
     * fines du backoffice via la capacité sentinelle `admin.full-access`
     * (couvre aussi les capacités ajoutées ultérieurement).
     *
     * Ignoré tant que la table pivot n'existe pas (appels précoces de ce seeder
     * depuis les migrations de 2026-07).
     */
    private function grantFullAccessToAdmins(): void
    {
        if (! Schema::hasTable('admin_permission_user')) {
            return;
        }

        $fullAccessId = Permission::where('name', 'admin.full-access')->value('id');

        if (! $fullAccessId) {
            return;
        }

        User::where('role', 'admin')->orderBy('id')->pluck('id')->each(function ($userId) use ($fullAccessId) {
            DB::table('admin_permission_user')->updateOrInsert(
                ['user_id' => $userId, 'permission_id' => $fullAccessId],
                ['created_at' => now()],
            );
        });
    }
}
